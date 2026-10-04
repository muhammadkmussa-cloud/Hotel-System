const port = process.env.HOTEL_TEST_PORT ?? '8137';

if (!/^\d{4,5}$/.test(port) || Number(port) < 1024 || Number(port) > 65535) {
  throw new Error('HOTEL_TEST_PORT must be an integer from 1024 to 65535.');
}

export const address = `127.0.0.1:${Number(port)}`;
export const origin = `http://${address}`;
