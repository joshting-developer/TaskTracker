// Browser-only synthetic fixtures. Production always exposes the pending verification step.
export * from '../../src/api/index.js';
import { api as actual } from '../../src/api/index.js';
export const api = { ...actual, async register(input) {
  const result = await actual.register(input);
  return result.verificationRequired ? actual.verifyEmail({ code: 'local666' }) : result;
} };
