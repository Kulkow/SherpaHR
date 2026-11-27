import { defineConfig } from '@hey-api/openapi-ts';

export default defineConfig({
  input: '../contracts/openapi.yaml',
  output: process.env.CONTRACT_OUTPUT ?? 'src/project-desk/contract',
  plugins: ['@hey-api/typescript'],
});
