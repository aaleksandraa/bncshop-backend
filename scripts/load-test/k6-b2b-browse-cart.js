import http from "k6/http";
import { check, sleep } from "k6";
import { Counter, Trend } from "k6/metrics";

/**
 * B2B browse/cart load scenario.
 *
 * Requires a real B2B session cookie (Sanctum). Obtain one by logging in via browser
 * or curl, then pass:
 *   B2B_SESSION=<laravel_session_value> k6 run scripts/load-test/k6-b2b-browse-cart.js
 *
 * Optional:
 *   API_URL=http://localhost:8000/api/v1
 *   B2B_XSRF=<xsrf_token>
 */

const apiBase = __ENV.API_URL || "http://localhost:8000/api/v1";
const session = __ENV.B2B_SESSION || "";
const xsrf = __ENV.B2B_XSRF || "";

const catalogDuration = new Trend("b2b_catalog_duration", true);
const cartDuration = new Trend("b2b_cart_duration", true);
const errors = new Counter("b2b_errors");

export const options = {
  scenarios: {
    b2bBrowse: {
      executor: "constant-vus",
      vus: 20,
      duration: "1m",
      exec: "browseB2bCatalog",
    },
  },
  thresholds: {
    b2b_catalog_duration: ["p(95)<400"],
    b2b_cart_duration: ["p(95)<300"],
    http_req_failed: ["rate<0.05"],
  },
};

function authHeaders() {
  const headers = {
    Accept: "application/json",
    "Content-Type": "application/json",
  };

  if (session) {
    headers.Cookie = `laravel_session=${session}` + (xsrf ? `; XSRF-TOKEN=${xsrf}` : "");
  }

  if (xsrf) {
    headers["X-XSRF-TOKEN"] = decodeURIComponent(xsrf);
  }

  return headers;
}

export function browseB2bCatalog() {
  if (!session) {
    errors.add(1);
    return;
  }

  const headers = authHeaders();

  const categoriesRes = http.get(`${apiBase}/b2b/categories`, { headers });
  if (!check(categoriesRes, { "b2b categories 200": (r) => r.status === 200 })) {
    errors.add(1);
  }

  const productsRes = http.get(`${apiBase}/b2b/products?per_page=30`, { headers });
  catalogDuration.add(productsRes.timings.duration);
  if (!check(productsRes, { "b2b products 200": (r) => r.status === 200 })) {
    errors.add(1);
    sleep(1);
    return;
  }

  const cartRes = http.get(`${apiBase}/b2b/cart`, { headers });
  cartDuration.add(cartRes.timings.duration);
  check(cartRes, { "b2b cart 200": (r) => r.status === 200 });

  const quoteRes = http.get(`${apiBase}/b2b/shipping-quote`, { headers });
  check(quoteRes, { "b2b shipping quote 200": (r) => r.status === 200 });

  sleep(1);
}
