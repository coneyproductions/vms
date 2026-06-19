const assert = require('node:assert/strict');
const path = require('node:path');

const {
  ADMISSIONS_SESSION_EXPIRED_MESSAGE,
  ADMISSIONS_REQUEST_FAILURE_MESSAGE,
  ADMISSIONS_NETWORK_FAILURE_MESSAGE,
  normalizeAdmissionsRestPayload,
  normalizeAdmissionsFetchResponse,
  performAdmissionsRequest,
} = require(path.resolve(__dirname, '../assets/js/vms-admissions-admin.js'));

const jsonResponse = (status, payload) => ({
  status,
  text: async () => JSON.stringify(payload),
});

const textResponse = (status, body) => ({
  status,
  text: async () => body,
});

(async () => {
  const successPayload = { ok: true, data: { items: [] }, error: null };
  assert.strictEqual(
    normalizeAdmissionsRestPayload(successPayload, { status: 200 }, ADMISSIONS_REQUEST_FAILURE_MESSAGE),
    successPayload,
    'Successful VMS responses should be preserved.'
  );

  const validationFailure = normalizeAdmissionsRestPayload({
    ok: false,
    error: {
      code: 'invalid_guest_name',
      message: 'Guest name is required.',
    },
  }, { status: 400 }, ADMISSIONS_REQUEST_FAILURE_MESSAGE);
  assert.equal(validationFailure.error.message, 'Guest name is required.', 'VMS validation errors should preserve their specific message.');

  const nativeNonceFailure = await normalizeAdmissionsFetchResponse(jsonResponse(403, {
    code: 'rest_cookie_invalid_nonce',
    message: 'Cookie check failed',
    data: {
      status: 403,
    },
  }), ADMISSIONS_REQUEST_FAILURE_MESSAGE);
  assert.equal(nativeNonceFailure.error.message, ADMISSIONS_SESSION_EXPIRED_MESSAGE, 'Native WordPress nonce failures should normalize to the exact expired-session message.');

  const vmsNonceFailure = await normalizeAdmissionsFetchResponse(jsonResponse(403, {
    code: 'vms_admission_bad_nonce',
    message: 'Your Admissions session expired. Refresh the page and try again.',
    data: {
      status: 403,
    },
  }), ADMISSIONS_REQUEST_FAILURE_MESSAGE);
  assert.equal(vmsNonceFailure.error.message, ADMISSIONS_SESSION_EXPIRED_MESSAGE, 'VMS nonce failures should normalize to the exact expired-session message.');

  const forbiddenFailure = await normalizeAdmissionsFetchResponse(jsonResponse(403, {
    code: 'vms_admission_forbidden',
    message: 'Access denied.',
    data: {
      status: 403,
    },
  }), ADMISSIONS_REQUEST_FAILURE_MESSAGE);
  assert.equal(forbiddenFailure.error.message, 'Access denied.', 'Ordinary permission failures should preserve their original message.');

  const nonJsonFailure = await normalizeAdmissionsFetchResponse(textResponse(500, '<html><body>Internal Server Error</body></html>'), ADMISSIONS_REQUEST_FAILURE_MESSAGE);
  assert.equal(nonJsonFailure.error.message, ADMISSIONS_REQUEST_FAILURE_MESSAGE, 'Non-JSON server failures should fall back to the request failure message.');

  const networkFailure = await performAdmissionsRequest(
    () => Promise.reject(new Error('offline')),
    'https://example.test/wp-json/vms/v1/admissions',
    { method: 'GET' },
    ADMISSIONS_REQUEST_FAILURE_MESSAGE,
    ADMISSIONS_NETWORK_FAILURE_MESSAGE
  );
  assert.equal(networkFailure.error.message, ADMISSIONS_NETWORK_FAILURE_MESSAGE, 'Network failures should normalize to the network failure message.');

  console.log('Admissions JS normalization OK.');
})().catch((error) => {
  console.error(error && error.stack ? error.stack : String(error));
  process.exit(1);
});
