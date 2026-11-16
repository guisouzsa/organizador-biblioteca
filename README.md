# Biblioteca Escolar

## Configuração do ambiente (Supabase)

1. Copie `.env.example` para `.env.local`.
2. Preencha as variáveis (valores em *Connect* no painel do projeto Supabase):
   - `NEXT_PUBLIC_SUPABASE_URL`: URL do projeto (`https://<ref>.supabase.co`).
   - `NEXT_PUBLIC_SUPABASE_ANON_KEY`: chave publicável (`sb_publishable_...`).
   - `SUPABASE_SECRET_KEY`: opcional; somente servidor, para operações administrativas. Nunca use o prefixo `NEXT_PUBLIC_` nem importe em componentes client-side.
3. Valide a conexão (somente leitura, não altera o banco):

```bash
npm run check:supabase
```

`.env.local` é ignorado pelo Git. Nunca commite chaves.

---

This is a [Next.js](https://nextjs.org) project bootstrapped with [`create-next-app`](https://nextjs.org/docs/app/api-reference/cli/create-next-app).

## Getting Started

First, run the development server:

```bash
npm run dev
# or
yarn dev
# or
pnpm dev
# or
bun dev
```

Open [http://localhost:3000](http://localhost:3000) with your browser to see the result.

You can start editing the page by modifying `app/page.tsx`. The page auto-updates as you edit the file.

This project uses [`next/font`](https://nextjs.org/docs/app/building-your-application/optimizing/fonts) to automatically optimize and load [Geist](https://vercel.com/font), a new font family for Vercel.

## Learn More

To learn more about Next.js, take a look at the following resources:

- [Next.js Documentation](https://nextjs.org/docs) - learn about Next.js features and API.
- [Learn Next.js](https://nextjs.org/learn) - an interactive Next.js tutorial.

You can check out [the Next.js GitHub repository](https://github.com/vercel/next.js) - your feedback and contributions are welcome!

## Deploy on Vercel

The easiest way to deploy your Next.js app is to use the [Vercel Platform](https://vercel.com/new?utm_medium=default-template&filter=next.js&utm_source=create-next-app&utm_campaign=create-next-app-readme) from the creators of Next.js.

Check out our [Next.js deployment documentation](https://nextjs.org/docs/app/building-your-application/deploying) for more details.
