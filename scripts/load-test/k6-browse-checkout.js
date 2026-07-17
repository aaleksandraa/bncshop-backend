import http from "k6/http";
import { check, sleep } from "k6";
import { Counter, Trend } from "k6/metrics";

const apiBase = __ENV.API_URL || "http://localhost:8000/api/v1";
const frontendBase = __ENV.FRONTEND_URL || "http://localhost:3000";

const plpDuration = new Trend("plp_duration", true);
const searchDuration = new Trend("search_duration", true);
const cartDuration = new Trend("cart_duration", true);
const errors = new Counter("errors");

export const options = {
  scenarios: {
    browse: {
      executor: "constant-vus",
      vus: 200,
      duration: "2m",
      exec: "browseCatalog",
    },
    checkout: {
      executor: "constant-arrival-rate",
      rate: 20,
      timeUnit: "1m",
      duration: "2m",
      preAllocatedVUs: 10,
      maxVUs: 30,
      exec: "checkoutFlow",
      startTime: "30s",
    },
  },
  thresholds: {
    plp_duration: ["p(95)<500"],
    search_duration: ["p(95)<200"],
    cart_duration: ["p(95)<300"],
    http_req_failed: ["rate<0.05"],
  },
};

function randomSessionId() {
  return `load-${__VU}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export function browseCatalog() {
  const plpRes = http.get(`${apiBase}/products?per_page=24`);
  plpDuration.add(plpRes.timings.duration);
  if (!check(plpRes, { "plp status 200": (r) => r.status === 200 })) {
    errors.add(1);
  }

  const searchRes = http.get(`${apiBase}/search?q=telefon&per_page=24`);
  searchDuration.add(searchRes.timings.duration);
  if (!check(searchRes, { "search status 200": (r) => r.status === 200 })) {
    errors.add(1);
  }

  const homeRes = http.get(frontendBase);
  check(homeRes, { "frontend status 200": (r) => r.status === 200 });

  sleep(1);
}

export function checkoutFlow() {
  const sessionId = randomSessionId();
  const headers = {
    "Content-Type": "application/json",
    "X-Cart-Session": sessionId,
  };

  const productsRes = http.get(`${apiBase}/products?per_page=1`);
  if (productsRes.status !== 200) {
    errors.add(1);
    return;
  }

  const body = JSON.parse(productsRes.body);
  const product = body?.data?.[0];
  if (!product?.id) {
    errors.add(1);
    return;
  }

  const addRes = http.post(
    `${apiBase}/cart/items`,
    JSON.stringify({ product_id: product.id, quantity: 1 }),
    { headers },
  );
  cartDuration.add(addRes.timings.duration);
  if (!check(addRes, { "cart add 200/201": (r) => r.status === 200 || r.status === 201 })) {
    errors.add(1);
    return;
  }

  const quoteRes = http.post(
    `${apiBase}/checkout/shipping-quote`,
    JSON.stringify({ shipping_method: "pickup" }),
    { headers },
  );
  check(quoteRes, { "shipping quote ok": (r) => r.status === 200 });

  sleep(0.5);
}
