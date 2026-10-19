import type { Metadata } from 'next';
import { getPublicMenu, ApiError } from '@/lib/api';
import { PublicMenuClient } from '@/components/PublicMenuClient';
import { MenuErrorView } from '@/components/MenuErrorView';

interface PageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ lang?: string; mesa?: string }>;
}

export async function generateMetadata({
  params,
  searchParams,
}: PageProps): Promise<Metadata> {
  const { slug } = await params;
  const { lang, mesa } = await searchParams;

  try {
    const restaurant = await getPublicMenu(slug, { lang, mesa });
    const title = `${restaurant.nombre} - Menú Digital`;
    const description =
      restaurant.eslogan ||
      restaurant.descripcion ||
      `Menú digital oficial de ${restaurant.nombre}`;

    return {
      title,
      description,
      openGraph: {
        title,
        description,
        images: restaurant.logo_url ? [restaurant.logo_url] : [],
      },
    };
  } catch {
    return {
      title: 'Menú Digital - MENUONL',
      description: 'Menú digital para restaurantes con pedidos directos a WhatsApp',
    };
  }
}

export default async function PublicMenuAliasRoute({
  params,
  searchParams,
}: PageProps) {
  const { slug } = await params;
  const { lang, mesa } = await searchParams;

  try {
    const restaurant = await getPublicMenu(slug, { lang, mesa, revalidate: 0 });

    return (
      <PublicMenuClient
        restaurant={restaurant}
        initialMesa={mesa}
      />
    );
  } catch (error) {
    if (error instanceof ApiError) {
      return (
        <MenuErrorView
          status={error.status}
          message={error.message}
          slug={slug}
        />
      );
    }

    const message =
      error instanceof Error ? error.message : 'Error desconocido al cargar el menú';

    return <MenuErrorView status={500} message={message} slug={slug} />;
  }
}
