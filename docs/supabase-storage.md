# Supabase Storage — estratégia futura

Status: **não implementado**. Este documento só registra a abordagem recomendada para capas e imagens.

## Abordagem recomendada

Usar o endpoint **S3-compatível** do Supabase Storage com o driver `s3` nativo do Laravel (Flysystem). Assim o upload passa pela API `Storage::disk(...)` do próprio Laravel, sem SDK de terceiros do Supabase.

1. Criar um bucket (ex.: `covers`) no painel do Supabase. Capas de livros podem ficar em bucket público; arquivos sensíveis, em bucket privado com URLs assinadas.
2. Gerar chaves de acesso S3 em Project Settings → Storage (S3 Connection) e guardá-las **somente** no `.env` (servidor).
3. Adicionar `league/flysystem-aws-s3-v3` (única dependência nova) e declarar um disco `supabase` em `config/filesystems.php` com `endpoint`, `region`, `bucket`, `key`, `secret` e `use_path_style_endpoint => true`.
4. Salvar apenas o caminho do arquivo no banco (coluna do Eloquent) e gerar a URL em tempo de execução.

## Segurança

- Nenhuma chave de Storage vai para o frontend ou para o Git.
- O upload é feito pelo servidor (Laravel); o navegador envia o arquivo ao Laravel, que valida via Form Request (tipo, tamanho) antes de gravar.
- Confirmar os parâmetros exatos do endpoint S3 na documentação oficial do Supabase no momento da implementação.
