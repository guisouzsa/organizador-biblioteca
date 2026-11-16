// Teste de conectividade com o projeto Supabase (somente leitura).
// Uso: npm run check:supabase
//
// - Não cria tabelas, não executa INSERT/UPDATE/DELETE, não altera RLS.
// - Nunca imprime chaves; apenas status HTTP e diagnóstico.
import { createClient } from "@supabase/supabase-js";

try {
  process.loadEnvFile(".env.local");
} catch {
  console.warn("Aviso: .env.local não encontrado; usando variáveis do ambiente.");
}

const url = process.env.NEXT_PUBLIC_SUPABASE_URL?.trim();
const key = process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY?.trim();

let failed = false;
const ok = (msg) => console.log(`  [OK]    ${msg}`);
const warn = (msg) => console.log(`  [AVISO] ${msg}`);
const fail = (msg) => {
  failed = true;
  console.log(`  [FALHA] ${msg}`);
};

console.log("Verificação de conexão Supabase\n");

// 1. URL configurada
let parsed;
if (!url) {
  fail("NEXT_PUBLIC_SUPABASE_URL não está configurada (vazia).");
} else {
  try {
    parsed = new URL(url);
    if (parsed.protocol !== "https:") throw new Error("protocolo não é https");
    ok(`URL configurada: ${parsed.origin}`);
  } catch (e) {
    fail(`NEXT_PUBLIC_SUPABASE_URL inválida (${e.message}).`);
  }
}

// 2. Chave publicável configurada
if (!key) {
  fail("NEXT_PUBLIC_SUPABASE_ANON_KEY não está configurada (vazia).");
} else if (key.startsWith("sb_secret_")) {
  fail("A chave configurada é uma SECRET key. Use somente a chave publicável aqui.");
} else if (key.startsWith("sb_publishable_")) {
  ok("Chave publicável configurada (formato sb_publishable_*).");
} else {
  warn("Chave em formato não reconhecido (esperado sb_publishable_*; JWT legado anon é aceito).");
}

if (failed || !parsed || !key) {
  console.log("\nResultado: NÃO VALIDADO — corrija a configuração acima e execute novamente.");
  process.exit(1);
}

// 3. Instância do cliente
try {
  createClient(parsed.origin, key, { auth: { persistSession: false } });
  ok("Instância do cliente Supabase criada.");
} catch (e) {
  fail(`Não foi possível criar o cliente: ${e.message}`);
  process.exit(1);
}

// Requisição GET genérica com diferenciação de erros
async function probe(label, path, extraHeaders = {}) {
  const target = `${parsed.origin}${path}`;
  try {
    const res = await fetch(target, {
      method: "GET",
      headers: { apikey: key, ...extraHeaders },
      signal: AbortSignal.timeout(15_000),
    });
    return { label, path, status: res.status, res };
  } catch (e) {
    const code = e.cause?.code ?? e.name;
    return { label, path, networkError: code };
  }
}

// 4. Chamadas reais à API do projeto
console.log("\nChamadas à API:");

const health = await probe("Auth health", "/auth/v1/health");
if (health.networkError) {
  const code = health.networkError;
  if (code === "ENOTFOUND") {
    fail(`DNS não resolve ${parsed.host} — URL do projeto provavelmente incorreta.`);
  } else if (code === "TimeoutError" || code === "ECONNRESET" || code === "ETIMEDOUT") {
    fail(`Problema de rede/tempo esgotado ao contatar o projeto (${code}).`);
  } else {
    fail(`Erro de rede ao contatar o projeto (${code}).`);
  }
  console.log("\nResultado: NÃO VALIDADO (falha de rede/URL).");
  process.exit(1);
}

console.log(`  GET /auth/v1/health -> HTTP ${health.status}`);
if (health.status === 200) {
  ok("Serviço Auth do projeto respondeu (projeto alcançável e URL correta).");
} else if (health.status === 401 || health.status === 403) {
  fail("Credenciais rejeitadas pelo projeto (chave publicável inválida/revogada).");
} else if (health.status === 404) {
  fail("Endpoint não encontrado — a URL provavelmente não pertence a um projeto Supabase.");
} else {
  fail(`Resposta inesperada do Auth: HTTP ${health.status}.`);
}

// Auth settings exige chave válida: comprova credenciais (200 = aceita, 401 = rejeitada).
const settings = await probe("Auth settings", "/auth/v1/settings");
if (settings.networkError) {
  fail(`Erro de rede ao validar credenciais (${settings.networkError}).`);
} else {
  console.log(`  GET /auth/v1/settings -> HTTP ${settings.status}`);
  if (settings.status === 200) {
    ok("Chave publicável aceita pelo projeto (credenciais válidas).");
  } else if (settings.status === 401 || settings.status === 403) {
    fail("Chave publicável rejeitada pelo projeto (credencial inválida).");
  } else {
    fail(`Resposta inesperada ao validar credenciais: HTTP ${settings.status}.`);
  }
}

// Data API: a raiz só aceita secret key; uma tabela inexistente prova que a
// requisição atravessa o gateway e chega ao PostgREST (404 PGRST205 != falha de conexão).
async function bodyOf(r) {
  try {
    return await r.res.json();
  } catch {
    return {};
  }
}

const rest = await probe("Data API", "/rest/v1/__verificacao_inexistente__?select=*");
if (rest.networkError) {
  fail(`Erro de rede na Data API (${rest.networkError}).`);
} else {
  const body = await bodyOf(rest);
  console.log(`  GET /rest/v1/<tabela inexistente> -> HTTP ${rest.status}${body.code ? ` (${body.code})` : ""}`);
  if (rest.status === 404 && body.code === "PGRST205") {
    ok("Data API (PostgREST) respondeu: tabela inexistente é esperado, não é falha de conexão.");
  } else if (rest.status === 200) {
    ok("Data API respondeu HTTP 200.");
  } else if (rest.status === 401 && /invalid api key/i.test(body.message ?? "")) {
    fail("Data API rejeitou a chave (Invalid API key).");
  } else if (rest.status === 401 || rest.status === 403) {
    warn(`Data API negou acesso (HTTP ${rest.status}): permissão/RLS, não falha de conexão.`);
  } else {
    fail(`Resposta inesperada da Data API: HTTP ${rest.status}.`);
  }
}

console.log(
  failed
    ? "\nResultado: FALHA na validação (veja itens acima)."
    : "\nResultado: conexão com o projeto Supabase VALIDADA.",
);
process.exit(failed ? 1 : 0);
