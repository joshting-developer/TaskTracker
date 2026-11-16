import assert from 'node:assert/strict';
import { resolveConfig } from 'vite';
const keys = ['VITE_BUILD_TARGET','VITE_SITE_ORIGIN','VITE_APP_ORIGIN'];
const original = Object.fromEntries(keys.map(key => [key,process.env[key]]));
function configure(values) {
  for (const key of keys) {
    if (values[key] === undefined) delete process.env[key];
    else process.env[key] = values[key];
  }
}
try {
  const invalid = [
    {mode:'production',values:{VITE_BUILD_TARGET:'unknown'}},
    {mode:'public',values:{VITE_SITE_ORIGIN:'https://www.example.test'}},
    {mode:'public',values:{VITE_APP_ORIGIN:'https://app.example.test'}},
    {mode:'app',values:{}},
    {mode:'public',values:{VITE_APP_ORIGIN:'https://app.example.test/path',VITE_SITE_ORIGIN:'https://www.example.test'}},
    {mode:'public',values:{VITE_APP_ORIGIN:'https://same.example.test',VITE_SITE_ORIGIN:'https://same.example.test'}},
    {mode:'app',values:{VITE_SITE_ORIGIN:'javascript:invalid'}},
  ];
  for (const row of invalid) {
    configure(row.values);
    await assert.rejects(resolveConfig({mode:row.mode,logLevel:'silent'},'build'));
  }
  configure({});
  const combined = await resolveConfig({logLevel:'silent'},'build');
  assert.ok(combined.build.rollupOptions.input.app && combined.build.rollupOptions.input.landing);
  assert.equal(combined.build.outDir,'dist');
  console.log('PASS: seven invalid split configurations rejected; default remains combined');
} finally { configure(original); }
