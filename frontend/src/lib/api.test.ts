import test, { describe } from 'node:test';
import assert from 'node:assert/strict';
import { getApiBaseUrl, getPublicMenu, ApiError } from './api';

describe('API Client', () => {
  test('getApiBaseUrl returns default and strips trailing slashes', () => {
    const originalEnv = process.env.NEXT_PUBLIC_API_URL;
    try {
      delete process.env.NEXT_PUBLIC_API_URL;
      assert.equal(getApiBaseUrl(), 'http://localhost:8000/api/v1');

      process.env.NEXT_PUBLIC_API_URL = 'http://api.example.com/api/v1/';
      assert.equal(getApiBaseUrl(), 'http://api.example.com/api/v1');

      process.env.NEXT_PUBLIC_API_URL = 'http://api.example.com/api/v1///';
      assert.equal(getApiBaseUrl(), 'http://api.example.com/api/v1');
    } finally {
      if (originalEnv === undefined) {
        delete process.env.NEXT_PUBLIC_API_URL;
      } else {
        process.env.NEXT_PUBLIC_API_URL = originalEnv;
      }
    }
  });

  test('getPublicMenu fetches successfully and parses data', async () => {
    const originalFetch = globalThis.fetch;
    const mockData = {
      id: 1,
      nombre: 'Test Restaurant',
      slug: 'test-restaurant',
      monograma: 'T',
      moneda: 'MXN',
      idioma: 'es',
      abierto: true,
      pedidos_activos: true,
      puede_pedir: true,
      categorias: [],
      destacados: [],
      horarios: [],
      contactos: [],
    };

    try {
      let requestedUrl = '';
      let requestedHeaders: HeadersInit | undefined;

      globalThis.fetch = (async (input: string | URL | Request, init?: RequestInit) => {
        requestedUrl = input.toString();
        requestedHeaders = init?.headers;
        return new Response(JSON.stringify({ data: mockData }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        });
      }) as typeof fetch;

      const result = await getPublicMenu('test-restaurant', { lang: 'en', mesa: 'Mesa-1' });

      assert.equal(result.id, 1);
      assert.equal(result.nombre, 'Test Restaurant');
      assert.ok(requestedUrl.includes('/menu/test-restaurant'));
      assert.ok(requestedUrl.includes('lang=en'));
      assert.ok(requestedUrl.includes('mesa=Mesa-1'));
      assert.equal((requestedHeaders as Record<string, string>)?.Accept, 'application/json');
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  test('getPublicMenu throws ApiError with 404 when restaurant is not found', async () => {
    const originalFetch = globalThis.fetch;
    try {
      globalThis.fetch = (async () => {
        return new Response(JSON.stringify({ message: 'Restaurante no encontrado o no disponible.' }), {
          status: 404,
          headers: { 'Content-Type': 'application/json' },
        });
      }) as typeof fetch;

      await assert.rejects(
        async () => {
          await getPublicMenu('non-existent');
        },
        (err: unknown) => {
          assert.ok(err instanceof ApiError);
          assert.equal(err.status, 404);
          assert.equal(err.message, 'Restaurante no encontrado o no disponible.');
          return true;
        }
      );
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  test('getPublicMenu throws ApiError with status code on 500 server error', async () => {
    const originalFetch = globalThis.fetch;
    try {
      globalThis.fetch = (async () => {
        return new Response(JSON.stringify({ message: 'Server error' }), {
          status: 500,
          headers: { 'Content-Type': 'application/json' },
        });
      }) as typeof fetch;

      await assert.rejects(
        async () => {
          await getPublicMenu('error-restaurant');
        },
        (err: unknown) => {
          assert.ok(err instanceof ApiError);
          assert.equal(err.status, 500);
          assert.equal(err.message, 'Server error');
          return true;
        }
      );
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  test('getPublicMenu throws ApiError with 502 when payload is missing data property', async () => {
    const originalFetch = globalThis.fetch;
    try {
      globalThis.fetch = (async () => {
        return new Response(JSON.stringify({ foo: 'bar' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        });
      }) as typeof fetch;

      await assert.rejects(
        async () => {
          await getPublicMenu('bad-payload');
        },
        (err: unknown) => {
          assert.ok(err instanceof ApiError);
          assert.equal(err.status, 502);
          return true;
        }
      );
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  test('getPublicMenu throws ApiError with status 0 on network connection failure', async () => {
    const originalFetch = globalThis.fetch;
    try {
      globalThis.fetch = (async () => {
        throw new TypeError('fetch failed: ECONNREFUSED');
      }) as typeof fetch;

      await assert.rejects(
        async () => {
          await getPublicMenu('offline-restaurant');
        },
        (err: unknown) => {
          assert.ok(err instanceof ApiError);
          assert.equal(err.status, 0);
          assert.ok(err.message.includes('fetch failed'));
          return true;
        }
      );
    } finally {
      globalThis.fetch = originalFetch;
    }
  });
});
