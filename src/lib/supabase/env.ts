// Leitura centralizada das variáveis públicas do Supabase.
// Somente variáveis NEXT_PUBLIC_*; a secret key nunca passa por aqui.

export function getSupabaseEnv() {
  const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
  const anonKey = process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY;

  if (!url || !anonKey) {
    throw new Error(
      "Variáveis do Supabase ausentes: defina NEXT_PUBLIC_SUPABASE_URL e " +
        "NEXT_PUBLIC_SUPABASE_ANON_KEY em .env.local (veja .env.example).",
    );
  }

  return { url, anonKey };
}
