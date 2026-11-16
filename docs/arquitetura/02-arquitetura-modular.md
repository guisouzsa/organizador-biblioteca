# 02 — Arquitetura modular

Estilo: **monólito modular leve** sobre as convenções do Laravel 12. Os módulos são **pastas dentro das camadas
padrão** (`Http/Controllers/<Modulo>`, `Services/<Modulo>`…), não pacotes separados. Isso preserva o código atual,
mantém `App\Models` plano (relacionamentos entre módulos são frequentes) e permite extrair módulos depois, se
algum dia for necessário.

## 1. Módulos e responsabilidades

| Módulo | Sigla | Responsabilidade | Depende de |
|---|---|---|---|
| **Catálogo** | CAT | Livros (edições), categorias/disciplinas, coleções, fontes, ISBN, consulta externa | — |
| **Acervo** | ACE | Exemplares, códigos de barras internos, localização, conservação, estado, histórico de movimentações | CAT |
| **Pessoas** | PES | Leitores (alunos/servidores) e usuários do sistema, perfis, permissões | — |
| **Circulação** | CIR | Empréstimo, devolução, renovação, reserva, atraso, histórico | ACE, PES, ADM (regras) |
| **Gestão** | GES | Painel, indicadores, relatórios | CAT, ACE, CIR, PES (somente leitura) |
| **Administração** | ADM | Configurações, regras de empréstimo, disciplinas/categorias, auditoria, usuários | PES |
| **Transversal** | TRV | Autenticação, autorização (Policies), auditoria, erros de domínio, enums | — |

```
            ┌────────────── TRV (auth · policies · auditoria · exceções) ──────────────┐
            │                                                                           │
   CAT ──► ACE ──► CIR ◄── PES                      ADM (regras de empréstimo) ──► CIR  │
    │       │       │       │                                                           │
    └───────┴───────┴───────┴──────────────► GES (leitura) ◄───────────────────────────┘
```

Regra de dependência: **setas só no sentido indicado**. `CAT` nunca importa `ACE`/`CIR`. `GES` apenas lê.
Consultas de leitura que cruzam módulos (ex.: "empréstimos ativos de um livro") ficam em *query objects* do
módulo consumidor, nunca em `Book`.

## 2. Estrutura de diretórios proposta

Existente em **negrito**; o restante é novo. Nada é criado até a fase correspondente (§10 do plano).

```
app/
├─ Enums/                       BookSourceProvider · VerificationStatus · CategoryType · CopyStatus ·
│                               CopyCondition · CopyEventType · BorrowerType · BorrowerStatus ·
│                               LoanOutcome · ReservationStatus · UserRole
├─ DTOs/
│  ├─ Catalog/                  BookData
│  ├─ Inventory/                RegisterCopiesData · RelabelCopyData
│  ├─ Circulation/              CheckoutData · ReturnData · ReserveData
│  └─ People/                   BorrowerData
├─ Exceptions/
│  ├─ **ExternalLookupException**
│  └─ Domain/                   DomainException (base) + uma classe por regra violada
├─ Http/
│  ├─ Controllers/
│  │  ├─ Auth/                  LoginController · PasswordResetController
│  │  ├─ Catalog/               **BookController** · **ScannerController** · **IsbnLookupController** · CategoryController
│  │  ├─ Inventory/             CopyController · LocationController · CopyLabelController
│  │  ├─ Circulation/           LoanController · ReturnController · RenewalController · ReservationController
│  │  ├─ People/                BorrowerController
│  │  ├─ Admin/                 UserController · LoanRuleController · SettingsController · AuditLogController
│  │  └─ Reports/               DashboardController · ReportController
│  ├─ Requests/<Modulo>/        **BookRequest** · **StoreBookRequest** · **UpdateBookRequest** · **LookupIsbnRequest** · …
│  └─ Resources/                CopyResource · LoanResource · BorrowerResource (apenas p/ JSON do balcão/scanner)
├─ Models/                      **Book** · **Category** · **BookCollection** · **BookSource** · **User**
│                               BookCopy · CopyBarcode · CopyEvent · Location · Borrower · Loan ·
│                               LoanRenewal · LoanRule · Reservation · AuditLog · LibrarySetting
├─ Policies/                    BookPolicy · BookCopyPolicy · BorrowerPolicy · LoanPolicy · ReservationPolicy ·
│                               UserPolicy · ReportPolicy · AuditLogPolicy
├─ Services/
│  ├─ Catalog/                  **IsbnValidatorService** · **GoogleBooksService** · **OpenLibraryService** ·
│  │                            **BookLookupService** · **MetadataComparisonService** · **BookCatalogService** · **Concerns/**
│  ├─ Inventory/                CopyRegistrationService · CopyLifecycleService · CopyBarcodeService
│  ├─ Circulation/              LoanService · ReturnService · RenewalService · ReservationService · LoanRuleResolver
│  ├─ People/                   BorrowerService · UserAccountService
│  └─ Admin/                    AuditLogger · SettingsService
├─ Jobs/                        ExpireReservations (agendado)
└─ Console/Commands/            **CheckDatabaseConnection**
```

Movimentação do código existente: apenas **mudança de namespace** (`git mv` + ajuste de `use`), em um commit
isolado e sem alteração de comportamento, no início da Fase 0. Não é pré-requisito para o restante.

## 3. Responsabilidade de cada camada

| Camada | Faz | **Não** faz |
|---|---|---|
| **Model** | Relacionamentos, *casts* (Enums), *scopes* de consulta, invariantes locais (ex.: `BookCopy::isCirculable()`) | Regras que envolvem 2+ entidades; transações; chamadas externas |
| **Controller** | Receber requisição, chamar **um** serviço, escolher resposta (view/redirect/JSON) | Regras de negócio, consultas complexas, montagem de HTML |
| **Form Request** | Validar formato/limites, **autorizar** (`authorize()` → Policy), normalizar entrada (`prepareForValidation`), produzir o DTO (`toDto()`) | Consultar regras de negócio que dependem de estado (ex.: "exemplar disponível") |
| **DTO** | Transportar dados tipados e imutáveis (`final readonly class`) entre Request e Service | Lógica, acesso ao banco |
| **Service** | Regras de negócio, **transações**, *locks*, auditoria e eventos de domínio; lança exceções de domínio | Conhecer HTTP (Request/Response/sessão) |
| **Policy** | "Este usuário pode executar esta ação neste recurso?" (por perfil e estado) | Validar dados de entrada |
| **Resource** | Serializar para JSON (balcão/scanner) sem expor campos internos | Lógica |
| **Enum** | Estados, tipos e transições permitidas (`CopyStatus::canTransitionTo()`) | Persistência |
| **Exceção de domínio** | Representar violação de regra com **código estável** e mensagem ao usuário | Carregar dados sensíveis |
| **Job/Event** | Processamento assíncrono/agendado (expirar reservas); notificações futuras | Regras centrais (ficam no Service) |

### Quando criar DTO, Service, Repository

- **DTO**: só quando o dado cruza Request→Service com **4+ campos** ou de várias origens (`BookData`, `CheckoutData`,
  `RegisterCopiesData`, `BorrowerData`). Para um ou dois identificadores, passar os argumentos direto.
- **Service**: quando há regra, transação ou mais de uma entidade. CRUD simples (ex.: `Location`) fica no controller
  com Form Request e Policy, **sem** serviço.
- **Repository**: **não** será criado. Eloquent + *scopes* + *query objects* atendem; uma camada extra só duplicaria
  a API do Eloquent.
- **Events/Listeners**: não para regras centrais (ficam transacionais no Service). Úteis depois para notificações.
- **Observers**: evitados para auditoria (efeito invisível); a auditoria é **explícita** via `AuditLogger`.

## 4. Fluxo de exemplo — emprestar um exemplar

```
Balcão (Blade/JS)
   │ POST /circulacao/emprestimos {copy_barcode, borrower_id}
   ▼
LoanController@store ── StoreLoanRequest ── authorize(): $user->can('create', Loan::class)
   │                       └─ rules(): formato dos campos
   │                       └─ toDto(): CheckoutData(copyBarcode, borrowerId, userId)
   ▼
LoanService::checkout(CheckoutData)            ← regra de negócio, tudo em UMA transação
   │  DB::transaction:
   │    1. localizar cópia por código (inclui códigos antigos)      → CopyNotFound
   │    2. BookCopy::lockForUpdate()                                 (serializa concorrentes)
   │    3. exigir copia circulável e sem empréstimo ativo            → CopyNotAvailable
   │    4. carregar Borrower; exigir status ativo                    → BorrowerNotEligible
   │    5. LoanRuleResolver::for($borrower): prazo, limite, atraso   → LoanRulesNotConfigured / LoanLimitReached / BorrowerHasOverdueLoans
   │    6. exigir que a reserva "pronta" (se houver) seja deste leitor → CopyHeldForAnotherBorrower
   │    7. Loan::create(...) (índice único parcial = última barreira)
   │    8. CopyEvent::create(type=loaned) + AuditLogger::record(...)
   ▼
LoanController: redirect com recibo  |  DomainException → resposta de erro de negócio (§08)
```

```php
// Esboço ilustrativo (não implementado nesta etapa)
final readonly class CheckoutData
{
    public function __construct(
        public string $copyBarcode,
        public int $borrowerId,
        public int $userId,
    ) {}
}

public function store(StoreLoanRequest $request, LoanService $loans): RedirectResponse
{
    $loan = $loans->checkout($request->toDto());

    return redirect()->route('loans.show', $loan)->with('status', 'Empréstimo registrado.');
}
```

O mesmo desenho vale para o catálogo existente: `StoreBookRequest` → `BookData` → `BookCatalogService::create/update`.

## 5. Tratamento transversal

- **Exceções de domínio** (`App\Exceptions\Domain\*`) estendem `DomainException` com `code()` estável, `userMessage()` em
  pt-BR e `httpStatus()`. O *handler* converte em redirect com erro (web) ou JSON `{code, message}` (AJAX).
  Falhas inesperadas viram mensagem genérica + `Log::error` com contexto, **sem** vazar detalhes.
- **Autorização**: Policies + `UserRole`; `authorize()` dos Requests chama a Policy. Rotas protegidas por `auth` e `can:`.
- **Auditoria**: `AuditLogger::record(event, subject, old, new)` chamado pelos Services dentro da mesma transação.
- **Transações**: sempre no Service; um Service público = uma unidade de trabalho.
- **Tempo**: `Carbon::now()` injetável (`Clock`/`Carbon::setTestNow`) para testar prazos e atrasos.
