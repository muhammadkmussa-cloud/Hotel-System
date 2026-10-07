const port = process.env.HOTEL_TEST_PORT ?? '8137';
const authedPort = process.env.HOTEL_TEST_AUTHED_PORT ?? '8138';

function requirePort(value, name) {
  if (!/^\d{4,5}$/.test(value) || Number(value) < 1024 || Number(value) > 65535) {
    throw new Error(`${name} must be an integer from 1024 to 65535.`);
  }
}

requirePort(port, 'HOTEL_TEST_PORT');
requirePort(authedPort, 'HOTEL_TEST_AUTHED_PORT');

export const address = `127.0.0.1:${Number(port)}`;
export const origin = `http://${address}`;
export const authedAddress = `127.0.0.1:${Number(authedPort)}`;
export const authedOrigin = `http://${authedAddress}`;
