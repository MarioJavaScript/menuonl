import { PublicMenuApiResponse, RestaurantData } from '@/types/menu';

const DEFAULT_API_URL = 'http://localhost:8000/api/v1';

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public data?: unknown
  ) {
    super(message);
    this.name = 'ApiError';
    Object.setPrototypeOf(this, ApiError.prototype);
  }
}

export interface GetPublicMenuOptions {
  lang?: string;
  mesa?: string;
  revalidate?: number | false;
}

/**
 * Resolves the base URL for API calls.
 * Removes any trailing slashes for clean URL concatenation.
 */
export function getApiBaseUrl(): string {
  const url = process.env.NEXT_PUBLIC_API_URL || DEFAULT_API_URL;
  return url.replace(/\/+$/, '');
}

/**
 * Fetches public restaurant menu data from the MENUONL Laravel REST API.
 *
 * @param slug - Restaurant unique slug
 * @param options - Optional query parameters (language, table) and caching options
 * @returns Promise resolving to RestaurantData
 * @throws ApiError when restaurant is not found (404) or on network/server errors
 */
export async function getPublicMenu(
  slug: string,
  options?: GetPublicMenuOptions
): Promise<RestaurantData> {
  const baseUrl = getApiBaseUrl();
  const url = new URL(`${baseUrl}/menu/${encodeURIComponent(slug)}`);

  if (options?.lang) {
    url.searchParams.set('lang', options.lang);
  }
  if (options?.mesa) {
    url.searchParams.set('mesa', options.mesa);
  }

  const fetchOptions: RequestInit = {
    headers: {
      Accept: 'application/json',
    },
  };

  if (options?.revalidate !== undefined) {
    fetchOptions.next = { revalidate: options.revalidate };
  }

  let res: Response;
  try {
    res = await fetch(url.toString(), fetchOptions);
  } catch (error) {
    if (error instanceof ApiError) {
      throw error;
    }
    throw new ApiError(
      error instanceof Error ? error.message : 'Error de conexión con la API',
      0,
      error
    );
  }

  if (res.status === 404) {
    let message = 'Restaurante no encontrado o no disponible.';
    try {
      const errJson = await res.json();
      if (errJson && typeof errJson === 'object' && 'message' in errJson && typeof errJson.message === 'string') {
        message = errJson.message;
      }
    } catch {
      // Use fallback message
    }
    throw new ApiError(message, 404);
  }

  if (!res.ok) {
    let message = `Error de API: ${res.status} ${res.statusText}`.trim();
    let errorData: unknown = undefined;
    try {
      errorData = await res.json();
      if (errorData && typeof errorData === 'object' && 'message' in errorData && typeof (errorData as { message: unknown }).message === 'string') {
        message = (errorData as { message: string }).message;
      }
    } catch {
      // Use fallback message
    }
    throw new ApiError(message, res.status, errorData);
  }

  const json = (await res.json()) as PublicMenuApiResponse;

  if (!json || typeof json !== 'object' || !json.data) {
    throw new ApiError('Respuesta de API inválida: falta el campo "data"', 502, json);
  }

  return json.data;
}
