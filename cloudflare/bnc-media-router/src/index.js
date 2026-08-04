const WIDTHS = [320, 640, 1280];

/**
 * @param {R2Object} object
 * @param {string} cacheControl
 */
function serveR2Object(object, cacheControl = "public, max-age=31536000, immutable") {
  const headers = new Headers();
  object.writeHttpMetadata(headers);
  headers.set("Cache-Control", cacheControl);
  headers.set("ETag", object.httpEtag);

  return new Response(object.body, { headers });
}

/**
 * @param {Response} response
 * @param {string} cacheControl
 */
function withCacheHeaders(response, cacheControl) {
  const headers = new Headers(response.headers);
  headers.set("Cache-Control", cacheControl);

  return new Response(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers,
  });
}

/**
 * @param {string} key
 * @param {number|null} requestedWidth
 */
function variantCandidates(key, requestedWidth) {
  if (!requestedWidth || Number.isNaN(requestedWidth)) {
    return [key];
  }

  const snapped = WIDTHS.find((width) => width >= requestedWidth);
  if (!snapped) {
    return [key];
  }

  const dot = key.lastIndexOf(".");
  if (dot === -1) {
    return [key];
  }

  const variantKey = `${key.slice(0, dot)}_${snapped}${key.slice(dot)}`;

  return [variantKey, key];
}

export default {
  /**
   * @param {Request} request
   * @param {{ MEDIA: R2Bucket, FALLBACK_ORIGINS?: string }} env
   */
  async fetch(request, env) {
    if (request.method !== "GET" && request.method !== "HEAD") {
      return new Response("Method Not Allowed", { status: 405 });
    }

    const url = new URL(request.url);
    let key = decodeURIComponent(url.pathname.slice(1));

    // Accept both /products/... and legacy /storage/products/... URLs.
    if (key.startsWith("storage/")) {
      key = key.slice("storage/".length);
    }

    if (!key || key.includes("..")) {
      return new Response("Not found", { status: 404 });
    }

    const requestedWidth = parseInt(url.searchParams.get("w") ?? "", 10);

    for (const candidate of variantCandidates(key, requestedWidth)) {
      const object = await env.MEDIA.get(candidate);

      if (object) {
        if (request.method === "HEAD") {
          const headers = new Headers();
          object.writeHttpMetadata(headers);
          headers.set("Cache-Control", "public, max-age=31536000, immutable");

          return new Response(null, { headers });
        }

        return serveR2Object(object);
      }
    }

    const fallbackOrigins = (env.FALLBACK_ORIGINS ?? "")
      .split(",")
      .map((origin) => origin.trim())
      .filter(Boolean);

    for (const origin of fallbackOrigins) {
      const fallbackUrl = `${origin.replace(/\/$/, "")}/storage/${key}${url.search}`;

      const response = await fetch(fallbackUrl, {
        method: request.method,
        cf: { cacheTtl: 3600 },
      });

      if (response.ok) {
        return withCacheHeaders(response, "public, max-age=3600");
      }
    }

    return new Response("Not found", { status: 404 });
  },
};
