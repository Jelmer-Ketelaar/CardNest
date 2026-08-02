# CardNest Production Readiness Report

Auditdatum: 2 augustus 2026

Groene auditbaseline bij aanvang: commit `c6935a2` op branch `develop`

Conclusie: **alleen gereed voor interne lokale tests; niet gereed voor externe beta of productie**

> Tijdens de audit wijzigde dezelfde worktree buiten deze opdracht. Een Artist-commit (`65b485c`) en daaropvolgende ongecommitte Catalog/Binder/Marketplace/frontend-WIP verschenen na de groene checks. Die wijzigingen zijn niet van deze audit en niet opgenomen in het groene releasebewijs. Alleen de drie production-readinessdocumenten zijn door deze audit gewijzigd.

## 1. Executive summary

CardNest heeft een serieus, goed gestructureerd MVP-fundament. De modulaire Symfony-backend, Vue-frontend, PostgreSQL-datalaag en Redis-gebaseerde workers draaien lokaal. De kernflow registeren → collectie beheren → aanbieden → fake checkout → verzenden → ontvangen → reviewen bestaat. Op de geaudite commit slagen 164 backendtests (1.318 assertions), 77 frontendtests en 17 Playwright-tests; PHPStan, PHP-CS-Fixer, ESLint, de productiebuild, Doctrine-validatie en dependency-audits zijn groen.

Dat bewijs is onvoldoende voor productie. De browsertests mocken alle API-calls en bewijzen dus geen full-stackflow. De containers starten developmentservers, er is geen staging- of productieomgeving, deploymentpipeline, restorebaar backupproces, centrale observability of veilig image-uploadpad. E-mailverificatie en wachtwoordherstel loggen tokens maar sturen geen echte e-mail; verificatie wordt bovendien niet afgedwongen. Marketplacefoto's zijn willekeurige externe HTTPS-URL's.

De ernstigste technische bevinding is een race tussen checkout en listing annuleren/verlopen. Checkout vergrendelt een listing, maar annuleren en de expiry-handler doen dat niet. Een stale cancel/expire-write kan daardoor een reeds verkochte listing en het collectie-item terugzetten, terwijl een betaalde order blijft bestaan. Ook ontbreekt het volledige refund-, dispute-, payout- en payment-webhook/reconciliationmodel. De huidige fake provider is nadrukkelijk geen basis voor echt geld.

De production-readinessscore is **22/60 (37%)**. Dat is de ongewogen, gelijk gewogen som van twaalf auditcategorieën, geen kansberekening of SLA. De aanbevolen eerstvolgende releasefase is **Phase A — Local development complete**; pas daarna volgt interne staging.

## 2. Overall production classification

**Classificatie: Production-ready for internal testing only.**

| Releasevorm | Status | Motivering |
|---|---|---|
| Lokale ontwikkelomgeving | Bruikbaar, nog niet volledig afgerond | Kernfunctionaliteit en geautomatiseerde checks werken, maar CI-configuratie, documentatie en echte full-stackdekking hebben gaten. |
| Interne staging | Niet gereed | Geen immutable productie-images, stagingconfiguratie, deployment, secrets, TLS, backups, readiness of observability. |
| Closed beta zonder echt geld | Niet gereed | Listingrace, externe foto-URL's, echte e-mail, privacyflows, support/moderatie en full-stack releasebewijs ontbreken. |
| Controlled beta met echt geld | Niet gereed | Geen echte paymentprovider, webhooks, durable idempotent orchestration/reconciliation, refunds, disputes, seller verification of payouts. |
| Publieke productie | Niet gereed | Alle bovenstaande blokkades plus juridische, operationele en schaalbaarheidsrisico's. |

## 3. Current architecture

CardNest is een modulaire monoliet met vier lokale runtimetypen:

```text
Vue 3/Vite frontend
        |
        | /api/v1 JSON + bearer token
        v
Symfony 7.4 LTS API ---- PostgreSQL 16
        |                       |
        +---- Redis ------------+
              | cache / locks / Messenger
              + worker
              + pricing scheduler
```

- Backend: PHP 8.4-doelversie, Symfony 7.4 LTS, Doctrine ORM/Migrations, Security, Validator, Messenger, Scheduler en Workflow.
- Frontend: Vue 3, TypeScript strict, Vue Router, Pinia, Vite en een eigen `fetch`-client.
- Publieke IDs: UUIDv7; geld: integer minor units met `EUR`.
- Modules volgen hoofdzakelijk `Domain/Application/Infrastructure/Presentation`.
- Lokale runtime: `compose.yaml` met backend, frontend, worker, pricing-scheduler, PostgreSQL en Redis.
- De backend is de bedrijfslogische bron van waarheid; controllers gebruiken request/response-DTO's in plaats van Doctrine-entiteiten rechtstreeks te serialiseren.

Productiearchitectuur is nog niet geïmplementeerd: er is geen reverse proxy, PHP-FPM, statische frontendserver, object storage, CDN, secrets manager, managed database/cache, staging- of productieconfiguratie.

## 4. Repository inventory

| Onderdeel | Waarneming |
|---|---|
| Backend | Circa 444 bestanden onder `backend`; 12 functionele modulemappen plus `Shared`. |
| Frontend | Circa 89 bestanden onder `frontend`; 20 eager-loaded pagina's en een centrale API-client/storelaag. |
| Database | 18 gemigreerde migrations op baseline; 35 publieke tabellen; schema synchroon met Doctrine. |
| Documentatie | Circa 18 documenten, inclusief productvisie, API, ADR's en productiechecklists. |
| Infrastructuur | `compose.yaml`, twee minimale development-Dockerfiles en één CI-workflow. |
| GitHub | Private repository, Project #7 met 19 velden en 162 items; statussen Todo/In Progress/In Review/Done aanwezig. |
| Open issues | Eén bestaand open issue gevonden: #198, “Manage focus in modal dialogs”, P1. |
| Branchbescherming | Niet configureerbaar/gecontroleerd via API: GitHub retourneerde 403 voor het private repository-plan. |

### Repository coverage matrix

`Y` betekent aantoonbaar aanwezig, `P` gedeeltelijk, `S` alleen scaffolding, `N` afwezig en `M` uitsluitend gemockte Playwright-dekking. “Production-ready” vereist meer dan alleen aanwezige bestanden.

| Module | Backend | Frontend | Migrations | Unit | Integration/API | E2E | Production-ready |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Identity | Y | P | Y | Y | Y | M | N |
| Catalog | Y | Y | Y | Y | Y | M | N |
| Artists | P/WIP | P/WIP | P/WIP | P/WIP | P/WIP | N | N |
| Collection | Y | Y | Y | Y | Y | M | N |
| Storage | Y | Y | Y | P | Y | M | N |
| Binder | Y | Y | Y | Y | Y | M | N |
| Wishlist | Y | Y | Y | Y | Y | M | N |
| Marketplace | Y | Y | Y | Y | Y | M | N |
| Cart | Y | Y | Y | Y | Y | M | N |
| Orders | Y | Y | Y | Y | Y | M | N |
| Payments | S | P | Y | Y | Y | M | N |
| Shipping | P | P | Y | P | Y | M | N |
| Reviews | Y | Y | Y | Y | Y | M | N |
| Pricing | P | P | Y | Y | Y | M | N |
| Images | S | S | N | N | N | N | N |
| Notifications | N | N | N | N | N | N | N |
| Moderation | N | N | N | N | N | N | N |
| Privacy | N | N | N | N | N | N | N |
| Administration | N | N | N | N | N | N | N |

Aanwezige backendmodules:

```text
Identity Catalog Collection Storage Binder Wishlist Marketplace
Order Payment Shipping Review Pricing Shared
```

Niet als productmodule aanwezig: Images, Notification, Moderation, Administration, Privacy, Reporting, Disputes en Payouts. Artists was op de groene baseline alleen een illustrator-string; commit `65b485c` voegde later een model/repository/API-basis toe en blijft in dit rapport gedeeltelijk/not verified.

### Concurrent worktree note

Na de groene baselinechecks verschenen buiten deze audit wijzigingen voor `Artist`, `CatalogCardArtist`, een repository/controller, frontendfilters en `Version20260802180000`. Een tussenstand had de migration nog niet toegepast en meldde een PHPStan-fout; daarna verscheen commit `65b485c` en begon nieuwe ongecommitte Catalog/Binder/Marketplace/frontend-WIP. Omdat de code tijdens de audit bleef veranderen, is deze post-baseline staat **not verified** in plaats van groen of defect verklaard. Herhaal alle releasechecks op één vast commit-ID nadat die WIP is afgerond.

## 5. Implemented functionality

### Complete

- Relationele scheiding tussen cataloguskaart en fysiek `CollectionItem`.
- UUID-identifiers en integer geldbedragen in de geïmplementeerde domeinen.
- Catalogusset- en kaartendpoints met filters en paginering.
- Eigenaarscontrole voor private collectie-, opslag-, binder-, wishlist-, listing- en orderresources.
- Atomair voorkomen van twee gelijktijdige checkouts op dezelfde listing; `CheckoutConcurrencyTest` bewijst dit scenario.
- Structured JSON-fouten, server-side validatie en DTO-responses.

“Complete” betekent hier compleet voor de huidige beperkte use case, niet productierijp als systeem.

### Mostly complete

- Collectie CRUD, archivering, quantity-naar-losse-items en duplicate-detectie.
- Hiërarchische opslag, actuele locatie en locatiehistorie.
- Binderpagina's, slots, placeholders en handmatige toewijzing.
- Wishlist CRUD, voortgang en matching op geregistreerde collectie-items.
- Fake checkout, orders per verkoper, verzending, ontvangst, import naar collectie en reviews.
- Catalogusimport en realistische lokale dataset (134.144 kaarten, 1.640 sets, 31 series op auditmoment).

### Partially implemented

- Identity: backend reset/verify bestaat, maar echte mail, frontend resetflow, profielbeheer en verificatie-enforcement ontbreken.
- Marketplace: listing lifecycle en verzending bestaan; seller verification, moderation, veilig beeldbeheer en conflictvrije cancel/expire ontbreken.
- Pricing: echte externe indicaties en dagelijkse tracked-card snapshots bestaan; verkochte platformprijzen, bron-SLA, rechten en schaalbare refresh ontbreken.
- Orders/payments: fake charge en gedeelde betaling bestaan; annuleren/refunds/reconciliation/webhooks/payouts ontbreken.
- Frontend: kernpagina's werken, maar foutgrenzen, toegankelijke dialogs, i18n, schaalbare paginering en productiebrowserdekking zijn onvolledig.

### Scaffold only

- Listingstatussen `pending_review` en `suspended` zonder review/suspend-use cases.
- `OrderStatus::Refunded` en `PaymentStatus::Refunded` zonder refundtransitie, provideractie of endpoint.
- Binder `layoutStrategy` wordt opgeslagen/getoond, maar voert geen automatische indeling uit.
- `docs/adr/0001-per-order-refunds-for-shared-payments.md` beschrijft een richting, geen werkende refundflow.
- Production-, backup- en privacydocumenten beschrijven verwachtingen, geen aantoonbare runtime of processen.

### Missing

- Productie image-upload/storage/scan/transformatie/CDN.
- Notifications, notification preferences en echte transactional email.
- Moderation, reports, disputes, fraud signals en adminhandelingen.
- GDPR-export, accountverwijdering/anonymisering, bewaartermijnjobs en consentbeheer.
- Seller onboarding/KYC, echte marketplace payments, refunds, payouts en chargebacks.
- Staging/production hosting, deploy/rollback, backups/restore, metrics/tracing/alerts en incident response.
- NL/EN-i18n, gebruikersadressen/-instellingen en frontend wachtwoordherstel.

### Broken or misleading

- `/api/v1/health` retourneert statisch 200 en bewijst geen database-, Redis- of configuratiereadiness; `HealthPage.vue` roept zelfs die endpoint niet aan.
- Een niet-geverifieerde gebruiker kan inloggen en private functies openen; dit is met een echte browser bevestigd.
- “Dagelijks ververst” geldt alleen voor tracked cards; een public price GET kan synchroon de provider aanroepen.
- 17 “E2E”-tests mocken ieder API-verzoek via `page.route().fulfill()` en zijn geen end-to-endbewijs tot PostgreSQL/Redis.
- Sellerfoto's lijken uploadbare listingfoto's, maar zijn alleen willekeurige externe HTTPS-URL's.
- `docs/release-verification.md` noemt verouderde aantallen (139 tests, 15 migrations, 16 E2E) tegenover actueel 164, 18 en 17.
- De API accepteert 19 `CardLanguage`-waarden, maar `chk_wishlist_language` accepteert er 10; geldige domeininput zoals `es-mx` of `pt-br` kan als databasefout/500 eindigen.

## 6. Module-by-module assessment

| Module | Status | Belangrijk bewijs en resterend risico |
|---|---|---|
| Identity | Partially implemented | `AuthController` biedt register/login/logout/forgot/reset/verify/resend en `/me`; `services.yaml` bindt productie-onafhankelijk aan logging-senders. `LoginUser` vereist geen verificatie. Frontend mist reset/profile/delete/settings. |
| Catalog | Mostly complete | `CardSetController`, `CatalogCardController`, TCGdex-provider en 134k lokale kaarten. Import is handmatig en 134.042/134.144 records hebben alleen summary-data met onder meer `Unknown` rarity/type en standaardvariant; zoekquery's en DTO-hydratatie veroorzaken scan/N+1-risico. |
| Artists | Partially implemented / not verified | Baseline bewaart alleen `CatalogCard.illustrator`; de latere commit `65b485c` voegt `Artist`, `CatalogCardArtist`, repository en API-routes toe. Volledige importdata, UI-flow, migration op schone DB en releasechecks zijn niet opnieuw bewezen. |
| Collection | Mostly complete | `CollectionItemController`, owner-scoped repositories, quantity en duplicate-tests. Foto's, condition details, grading en create+locatie-atomiciteit ontbreken. |
| Storage | Mostly complete | Hiërarchie, actuele locatie en historie bestaan met ownership/constraints. Frontend biedt descendants als mogelijke parent; backend moet cycles afwijzen. |
| Binder | Mostly complete | Pages/slots/placeholders/assignments en constraints bestaan. Automatische layouts ontbreken; zeer grote binders worden als één payload gerenderd. |
| Wishlist | Broken for extended languages | CRUD, criteria en collection matching bestaan. `CardLanguage` en DB-checkconstraint verschillen, waardoor meerdere aangeboden taalcodes op persist falen. Marketplace/prijsalerts ontbreken; herberekening na archive/correctie is niet overal aangetoond. |
| Marketplace | Partially implemented | Draft/publish/update/cancel/expire/search/shipping bestaan. Checkout-cancel/expiry-race, externe foto-URL's, hardcoded nul-servicefee, geen verification/moderation en niet-aangesloten statussen blokkeren release. |
| Cart | Mostly complete for fake checkout | Multi-sellercart, reservering en atomair checkoutpad bestaan. Frontendraces en echte payment orchestration ontbreken. |
| Orders | Partially implemented | Orders per verkoper, statusflows, itemimport en participant auth bestaan. Import maakt een nieuw fysiek `CollectionItem` in plaats van dezelfde fysieke identiteit over te dragen; daarnaast onbegrensde lists, onvolledige immutable snapshots en geen cancellation/refund/dispute. |
| Payments | Scaffold only | `PaymentProvider::charge()` met `FakePaymentProvider`; een deterministic idempotency key bestaat, maar geen durable intent, echte provider, webhook, refund, reconciliation of payout. Externe call staat binnen DB-transactie. |
| Shipping | Partially implemented | Mark-shipped/confirm-received en eenvoudige letter/parcel/pickup-prijzen. Geen carrier/tracking events, loss/return/supportflow. |
| Reviews | Mostly complete | Alleen na completed order, één review per reviewer/order en reputatie. Geen rapportage/moderatie/edit-audit. |
| Pricing | Partially implemented | `CardPriceController`, snapshots, scheduler en TCGdex-adapter voor Cardmarket-gelabelde velden. Alleen tracked cards dagelijks; geen `SoldPrice`, prijsdefinities/SLA/rechten en publieke-callbegrenzing. Ruwe catalogusmetadata kan bovendien providerpricing buiten de dedicated pricingresponse ontsluiten. |
| Images | Scaffold only | Catalogus gebruikt externe URLs en listing gebruikt URL-referenties. Geen object storage, uploads, MIME/pixel/malware/EXIF, thumbnails of takedown. |
| Notifications | Missing | Alleen async emailmessages met logging-senders; geen notifications/tabel/preferences/in-app UI. |
| Moderation | Missing | Geen reports, sanctions, dispute queue, fraud signals of moderator UI/auditlog. |
| Privacy | Missing as product capability | Documentatie bestaat, maar geen export/delete/anonymisation/consent/retention workflow of gebruikers-UI. |
| Administration | Missing | Geen adminroutes/-frontend, rollenbeheer, cataloguscorrecties, feeconfiguratie of auditlogviewer. |

### Identity

**Status: Partially implemented.** `backend/src/Identity/Presentation/Http/AuthController.php` en `CurrentUserController.php` leveren de kern-API; token- en ownerchecks zijn getest. `backend/config/services.yaml` selecteert echter logging-only mailadapters, `LoginUser` gate niet op verificatie en de frontend mist reset/profile/delete/settings.

### Catalog

**Status: Mostly complete.** `CardSetController`, `CatalogCardController`, Doctrine repositories en de TCGdex-adapter leveren sets/cards/filters/paginering. Import is handmatig, de bulk van de records bevat alleen summary-data en queryplannen tonen scan/N+1/deep-offsetrisico.

### Artists

**Status: Partially implemented / not verified.** Baseline `c6935a2` had alleen `CatalogCard.illustrator`; lokale commit `65b485c` voegde een entity/relation/repository/API-basis toe. Die post-baseline flow en verdere UI/import-WIP hebben geen vastgepinde volledige releasecheck.

### Collection

**Status: Mostly complete.** `CollectionItemController` en owner-scoped repositories ondersteunen create/list/get/update/archive, quantity, money en duplicates. Condition details, grading, first-party foto's en een atomaire item+locatie-edit ontbreken.

### Storage

**Status: Mostly complete.** Storage hierarchy, current placement en location history bestaan met ownerchecks en databaseconstraints. De UI kan een descendant als parent aanbieden; backendrejectie is geen goede preventieve UX.

### Binder

**Status: Mostly complete.** Pagina's, slots, placeholders, assignment en manual drag/clickflows bestaan. `layoutStrategy` voert niets automatisch uit, occupied-slot placeholderconsistentie is niet afgedwongen en grote binders zijn onbegrensde payloads.

### Wishlist

**Status: Broken for extended languages.** CRUD en collection matching bestaan, maar `CardLanguage` accepteert meer codes dan `chk_wishlist_language`. Marketplace-/prijsalerts en volledige herberekening na latere collectiecorrecties zijn niet bewezen.

### Marketplace

**Status: Partially implemented.** `ListingController`, `SellerListingController`, workflow en repositories leveren draft/publish/update/cancel/expire/search. Cancel/expiry serializeert niet met checkout; images zijn externe URL's; seller verification, moderation en feeconfiguratie ontbreken.

### Cart

**Status: Mostly complete for fake checkout.** `CartController` en `CheckoutCart` ondersteunen multi-seller grouping, reservations en locked checkout. De frontend heeft out-of-order delete-risico en de backend kent geen echte asynchronous payment window/recovery.

### Orders

**Status: Partially implemented.** Participant-scoped list/detail, seller-split orders, status history en import-to-collection bestaan. Lists zijn onbegrensd, orderitems missen volledige immutable card snapshots en eigendomsoverdracht/refunds/disputes ontbreken.

### Payments

**Status: Scaffold only.** `PaymentProvider`, `PaymentInstruction` en `FakePaymentProvider` bieden een deterministic key/fake reference. Er is geen durable pre-charge intent/attempt, provider/webhook/reconciliation/refund/payoutflow; `CheckoutCart` doet de side effect onder DB-locks.

### Shipping

**Status: Partially implemented.** `OrderShippingController` ondersteunt mark-shipped en confirm-received met eenvoudige shippingquotes. Carrier, tracking events, returns, loss en operationele fallback ontbreken.

### Reviews

**Status: Mostly complete for MVP.** `ReviewController`/`ReputationController` en constraints beperken reviews tot orderparticipants en één review per reviewer/order. Report/moderation/edit-audit ontbreekt.

### Pricing

**Status: Partially implemented.** `CardPriceController`, `PricingScheduleProvider`, snapshots, locking en dagelijkse uniqueness bestaan. Alleen tracked cards worden gepland; public cache misses kunnen upstream blokkeren; `SoldPrice`, retention/SLA en gebruiksrechten zijn open.

### Images

**Status: Scaffold only.** Catalogus en listings refereren externe URLs. Er is geen object storage, uploadownership, quarantine, MIME/pixel/size/malware/EXIF-validatie, derivative/CDN- of takedownflow.

### Notifications

**Status: Missing.** Er zijn Messengerberichten voor technische e-mail en pricing/listingwerk, maar geen Notification-entiteit, preferences, in-app inbox of order/security notificationcatalogus.

### Moderation

**Status: Missing.** Geen reports/disputes, moderationqueue, sanctions, risk events, attachment evidence, moderatorworkflow of audittrail is aanwezig.

### Privacy

**Status: Missing as product capability.** `docs/privacy.md` beschrijft intentie; export/delete/anonymise/consent/retention/legal-hold use cases, UI, jobs en oefenbaar proces ontbreken.

### Administration

**Status: Missing.** Rollen worden opgeslagen, maar er zijn geen least-privilege admin/moderator use cases, frontend, auditlog, cataloguscorrectie, fee/config- of supporttools.

### End-to-end implementation matrix

Deze matrix groepeert de 26 verplichte inspectielagen als volgt: API = route/controller; DTO = request/validatie/response; application = command/query/handler/domein; persistence = repository/mapping/migration; consistency = transactie/locking; auth = autorisatie; errors = foutafhandeling; UI = pagina/client/loading/empty/error; tests = unit/integration/API/frontend/E2E; docs = documentatie. `C` is compleet voor de huidige beperkte use case, `P` gedeeltelijk, `S` scaffolding, `B` aantoonbaar defect, `M` gemockt en `—` afwezig.

| Module | API | DTO | Application/domain | Persistence | Consistency | Auth | Errors | UI states | Tests | Docs | Ops |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Identity | C | C | P | C | C | C | C | P | P/M | P | — |
| Catalog | C | C | P | C | P | C | C | P | P/M | P | P |
| Artists | P | P | P | P | P | P | P | P | P | — | — |
| Collection | C | C | P | C | P | C | C | P | P/M | P | — |
| Storage | C | C | C | C | C | C | C | P | P/M | P | — |
| Binder | C | C | P | C | B | C | C | P | P/M | P | — |
| Wishlist | C | C | C | B | B | C | P | P | P/M | P | — |
| Marketplace | C | C | P | C | B | C | P | P | P/M | P | — |
| Cart | C | C | P | C | P | C | C | P | P/M | P | — |
| Orders | C | C | P | P | P | C | C | P | P/M | P | — |
| Payments | S | P | S | P | B | C | P | P | P/M | P | — |
| Shipping | P | C | P | P | P | C | C | P | P/M | P | — |
| Reviews | C | C | C | C | C | C | C | P | P/M | P | — |
| Pricing | P | C | P | C | P | C | P | P | P/M | P | P |
| Images | S | P | S | — | — | — | — | S | — | P | — |
| Notifications | — | — | — | — | — | — | — | — | — | P | — |
| Moderation | — | — | — | — | — | — | — | — | — | P | — |
| Privacy | — | — | — | — | — | — | — | — | — | P | — |
| Administration | — | — | — | — | — | — | — | — | — | P | — |

De matrix is een samenvatting; de status- en bewijsregels erboven en de blockeranalyse hieronder zijn leidend. Met name `C` bij een afzonderlijke laag mag niet als modulebrede productieclaim worden gelezen.

## 7. Critical user-flow assessment

| Flow | Result | Evidence / gap |
|---|---|---|
| Account | Partial | Echte browser: registratie, redirect naar login, login en logout werken. Auditaccount `production-audit-20260802-1849@example.com` is lokaal aangemaakt. De link wordt niet echt verzonden en verificatie wordt niet afgedwongen. |
| Collection | Mostly complete | API en UI ondersteunen create/view/update/archive, filters en quantity; wijziging van item en locatie gebeurt in twee commits en kan partial success geven. |
| Listing | Unsafe under concurrency | Publish valideert eigenaar/status/foto-URL's. `CancelListing` en `ExpireListingHandler` nemen geen zelfde lock als `CheckoutCart`. |
| Checkout | Partial | Deterministische pessimistische locks en concurrencytest voorkomen dubbele checkout. Alleen fake charge; providercall tijdens DB-transactie is onveilig voor echt geld. |
| Shipping | Partial | Alleen betaalde order kan verzonden worden en koper kan ontvangst bevestigen; tracking, loss, returns en disputes ontbreken. |
| Cancellation/refund | Missing | Geen controller/application service/provider method/migration. Enumwaarden zijn misleidende scaffolding. |
| Review | Mostly complete | Completed-ordercontrole, participantcontrole en unieke DB-constraint bestaan; moderation/reporting ontbreekt. |
| Artist | Not verified | Stable baseline heeft geen aparte artiestflow; gelijktijdige WIP is niet als productiebewijs geaccepteerd. |
| Price | Partial | Een snapshot wordt gelezen/ververst en historie getoond; SLA, volledige dekking, bronrechten, sample-definities en fault isolation ontbreken. |

## 8. Database and data-integrity findings

Positief bewijs:

- Geld wordt persistent als integer minor units plus currency opgeslagen; geen float-geldvelden gevonden.
- Belangrijke checks en unieke constraints bestaan: één collectie per owner, één actuele locatie, uniek binder-slot, één actieve reservering, één blocking listing per collectie-item, unieke listing per order-item en één review per reviewer/order.
- Foreign keys en statuschecks zijn ruim toegepast in de 18 baseline migrations.
- Checkout lockt buyer/cart/listings in deterministische volgorde; `CheckoutConcurrencyTest` bewijst dat twee kopers niet beide slagen.

Kritieke bevindingen:

1. **Stale-write race op listingstatus (P0).** `CheckoutCart` gebruikt `DoctrineListingRepository::lockForCheckout()`. `CancelListing` en `ExpireListingHandler` lezen zonder transactionele/pessimistische lock. Een al gelezen cancel/expire kan na checkout `sold` overschrijven naar `cancelled`/`expired` en het item terugzetten naar `in_collection` terwijl een paid order blijft bestaan.
2. **Onvolledige historische snapshots (P1).** `OrderItem` bewaart prijs en listingreferentie, maar `OrderResponse` leest naam/set/language/variant/condition opnieuw uit huidige catalogus-/collectieobjecten. Latere cataloguscorrecties kunnen historische orderweergave veranderen.
3. **Unbounded repositories (P1).** Orders, seller listings, wishlists en opslaglocaties hebben paden zonder begrensde paginering.
4. **Migratierisico (P1).** `Version20260802170000` deed vier brede data-UPDATEs; eerdere catalogusmigraties herschrijven/backfillen tabellen. Geen productievolume-rehearsal, lockbudget of expand-contractprocedure.
5. **Auto-migrate bij containerstart (P0 infra).** `compose.yaml` koppelt schemawijziging aan iedere backendstart; dat is niet veilig voor rollbacks of meerdere replicas.
6. **Wishlist language mismatch (P1).** `CardLanguage` bevat onder meer `es-mx`, `pt-br`, `pt-pt`, `zh-tw`, `zh-cn`, `pl`, `ru`, `id` en `th`; `Version20260731230000::chk_wishlist_language` staat alleen tien basiscodes toe. Geldige request/domainvalidatie kan dus eindigen in een onverwachte DB-exception.
7. **Fysieke identiteit wordt bij verkoop gekopieerd (P1/productbesluit).** `ImportOrderItemToCollection` maakt voor de koper een nieuw `CollectionItem` en archiveert het seller-item. Dit voldoet functioneel aan import-once per koper, maar niet letterlijk aan “één fysieke kaart, één digitaal object” met ownership history.
8. **Binderplaceholder kan inconsistent raken (P1).** `ConfigureBinderSlot` kan de gereserveerde cataloguskaart van een reeds bezet slot wijzigen; compatibiliteit wordt alleen bij `AssignBinderSlot` gecontroleerd.
9. **Niet iedere PHP-invariant is een DB-invariant (P1).** Collection language/condition/status/currency zijn niet allemaal door checks gedekt en de database kan niet bewijzen dat een gekozen `CardVariant` bij dezelfde `CatalogCard` hoort. Voeg gerichte constraints toe waar cross-rowmodellering dit veilig toelaat en behoud domeinvalidatie als eerste laag.

## 9. Security findings

Positief:

- Wachtwoorden worden met Symfony password hashing verwerkt.
- Auth-, verificatie- en resettokens worden als SHA-256-hashes met expiry bewaard.
- Authrate-limiters gebruiken gedeelde Redis-cache/locks.
- API-errors zijn gestructureerd; malformed JSON gaf veilig 400 zonder stacktrace.
- Objecteigendom is in de geïmplementeerde private use cases gecontroleerd.
- Stateless Authorization bearer-model betekent dat CSRF op de huidige JSON-mutaties niet de primaire bedreiging is.
- Een cross-origin preflight kreeg 405 zonder permissieve CORS-headers; dat is veilig voor het huidige same-originmodel. Kies later een expliciete allowlist als frontend en API echt verschillende origins krijgen.

Blokkades en hoge risico's:

- Bearertoken met 30 dagen geldigheid staat in `localStorage` (`frontend/src/stores/auth.ts`, `frontend/src/api/client.ts`); bij XSS is volledige accountovername mogelijk. Er is geen CSP.
- Responses missen HSTS, CSP, `X-Content-Type-Options`, frame-/referrer-/permissionspolicy; backend verraadt `X-Powered-By: PHP`.
- Geen globale/public API rate limiting; catalogus-, listing- en pricingroutes zijn enumerateerbaar.
- Productie gebruikt standaard logging-email-senders; verificatie/reset is niet leverbaar en tokenhash/emaildata worden gelogd.
- E-mailverificatie is niet vereist voor login of private acties.
- Sellerfoto-URL's worden server-side alleen op syntax/HTTPS gevalideerd; geen content-, MIME-, size-, malware- of ownershipcontrole. Derde partijen kunnen bezoekers volgen.
- Geen 2FA, seller verification, account restriction, auditlog, fraud signals of admin role-managementflow.
- Redis heeft lokaal geen auth/TLS; database-SSL/trusted hosts/proxies/secretsvalidatie voor productie zijn niet geconfigureerd.
- Composer/npm-audits en ingeschakelde Dependabot-alerts meldden op auditmoment geen bekende advisories. Code scanning en secret scanning waren echter niet ingeschakeld en er is geen repository-Dependabot-updateconfiguratie; “0 bekende advisories” is dus een momentopname, geen volledige supply-chaincontrole.

## 10. Performance findings

Een representatieve PostgreSQL-analyse op 134.144 cataloguskaarten liet zien:

- `lower(name) LIKE '%pikachu%'`: parallel sequential scan, circa 19,7 ms en 9.205 buffers.
- `lower(rarity) = ...`: ongeschikte raw index/queryvorm, circa 33,4 ms en 73k buffers.
- Diepe `OFFSET 100000`: sequential scan plus externe merge naar disk, circa 126,4 ms.
- Catalogusresponse bevat varianten en external references terwijl de basisquery alleen card/set/series joint; dit veroorzaakt waarschijnlijk circa twee lazy queries per kaart (N+1).
- De catalogusimport is handmatig en haalt voor bijna alle kaarten alleen summary-data binnen; een groot deel is daardoor nog niet geschikt voor betrouwbare rarity/type/variantfilters zonder verrijkingsstrategie.
- De database had 12 price snapshots voor 6 kaarten. Een letterlijke full-catalogrefresh met de huidige per-cardadapter vraagt tot circa 134.144 upstreamcalls en, bij twee varianten, circa 268.000 rijen per dag (~98 miljoen/jaar) vóór retentie/indexoverhead. Daarvoor is een bulkfeed plus capaciteits-/retentieontwerp nodig; tracked-card daily freshness is de realistische eerste SLA.

Frontend:

- Productiebuild is groen; één JS-chunk van 243,94 kB (75,63 kB gzip) en CSS 30,23 kB (6,21 kB gzip). Acceptabel voor de MVP, maar alle routes zijn eager geladen.
- Geen enkele image gebruikt systematisch `loading="lazy"`, expliciete dimensies, `srcset/sizes`, `decoding="async"` of foutfallback.
- Binders kunnen tot circa 7.200 slots in één payload/render bevatten.
- Selectors voor binder/listing laden maximaal de eerste 100 collection items; verdere items zijn functioneel onbereikbaar.
- Geen Lighthouse/Core Web Vitals-meting uitgevoerd: Lighthouse en een hostbrowser waren niet geïnstalleerd. Dit is **not verified**, niet groen.

Aanbevolen: projections/eager-loading, `pg_trgm`/expression indexes, cursorpaginering voor diepe lijsten, paginering in alle persoonlijke repositories, route-level code splitting en image delivery budgets. Eerst meten op productieachtige dataset en query-SLA's vastleggen.

## 11. Payment readiness

**Status: scaffold only; absoluut niet geschikt voor echt geld.**

Evidence:

- `PaymentProvider` biedt alleen `charge()`; `services.yaml` bindt aan `FakePaymentProvider`.
- `PaymentInstruction` bevat al een deterministic idempotency key en de fake reference is daarvan afgeleid. Er is echter geen vóór de side effect duurzaam opgeslagen intent/attempt, geen bewezen provider-idempotency, customer/account mapping, checkout session, webhook signature/inbox, reconciliation job, refund- of payoutactie.
- De providercall vindt binnen de databasecheckouttransactie en onder locks plaats. Een echte charge kan extern slagen terwijl de DB-commit faalt.
- Een gefaalde fake charge wordt door rollback niet als failed payment/auditrecord bewaard.
- Eén betaling kan meerdere seller-orders omvatten, maar er is geen geïmplementeerd allocation/refundmodel.
- `Refunded`-enumwaarden en ADR zijn geen werkende refund.
- Seller KYC, payout timing, reserve, chargeback, tax/VAT en buyer-protectionregels zijn externe open beslissingen.

Voor echt geld is een eventgedreven payment state machine nodig met provider-idempotency, gesigneerde webhooks, durable inbox/outbox, orderallocaties, partial/per-order refunds, reconciliation en handmatige recoverytools. Dit moet op provider-sandbox en gecontroleerde staging bewezen zijn.

## 12. Privacy and compliance readiness

**Status: onvoldoende voor externe gebruikers.**

- Geen user-facing privacy statement, terms/marketplace terms, cookiebeleid of consentrecord in de applicatie aangetoond.
- Geen data-export, correctieprofiel, delete/anonymise request, retention scheduler of privacy admincase.
- Geen dataclassificatie, verwerkersregister, DPA's of bewaarschema gekoppeld aan code/jobs.
- Financiële retentie versus recht op verwijdering is niet geïmplementeerd.
- Geen datalekrunbook of bewezen audit trail.
- Catalogus- en prijsdata en kaartafbeeldingen komen extern; gebruiksrechten, caching en presentatievoorwaarden zijn niet juridisch goedgekeurd.
- Productnaam en Pokémon/card imagery vereisen expliciete trademark/IP-review.

Broncontrole ondersteunt dat dit geen theoretische checkbox is: de [TCGdex-database gebruikt MIT-voorwaarden](https://github.com/tcgdex/cards-database/blob/master/LICENSE), maar die softwarelicentie verleent niet automatisch rechten op onderliggende kaartbeelden/merken; [Cardmarket vermeldt voorafgaande schriftelijke toestemming voor presentatie van kaarten en prijzen](https://www.cardmarket.com/en/FleshAndBlood/Policies/GeneralTermsAndConditions/GB); en [Pokémon Support vraagt projecten Pokémon-IP niet zonder toestemming te gebruiken](https://support.pokemon.com/hc/en-us/articles/360000634094-Can-I-use-Pok%C3%A9mon-images-or-materials). Laat Nederlandse/Belgische counsel de concrete toepassing beoordelen; dit rapport is geen juridisch advies.

Minimaal vóór closed beta: privacy-/termsreview, verwerkingsgrondslagen, privacyrequestflow, accountdeactivatie/anonymisatie, bewaarschema, subprocessors, image/price licensing en support-owner. Voor echt geld komen KYC/AML/provider- en fiscale verplichtingen erbij.

## 13. Infrastructure and deployment readiness

**Status: development only.**

- Backend draait `php -S`; frontend draait `vite dev`; broncode wordt via mutable bind mounts geplaatst.
- Dockerfiles kopiëren/bouwen de applicatie niet tot immutable non-root production artifacts.
- Geen Nginx/Caddy, TLS, compression, caching/SPA fallback, trusted proxy/host of security headers.
- Backend start automatisch migrations; geen pre-deploy job, backward-compatibilitygate of rollbackpad.
- Redis is ephemeral en zonder auth/TLS/persistence; queue- en schedulerstate kan bij replacement verdwijnen.
- Worker/scheduler hebben geen healthcheck/heartbeat; Messenger heeft geen failed transport/DLQ. De backendimage bevat geen aantoonbare `pcntl`-ondersteuning of expliciete graceful-shutdown/stop-grace configuratie.
- Er is geen transactional outbox. Registratie en listing-publicatie committen state en dispatchen daarna; een queuefout kan verificatiemail of listing-expiry permanent missen. Er is geen expiry-reconciliatiesweep.
- Geen staging-/productionmanifest, hosting, database pooling/SSL, object storage, secrets manager of environment validation.
- CI controleert tests/lint/build, maar niet audits, schema/fresh migration, Dockerbuild, secret/SAST/container scan, full-stack E2E of deployment.
- CI Postgres-service maakt `cardnest_test`, terwijl de basis-`DATABASE_URL` `cardnest` noemt. Dit is bewust gekoppeld via `when@test.doctrine.dbal.dbname_suffix: '_test...'`; het GitHub-log bewijst dat alle 18 migrations op een verse testdatabase draaiden. Documenteer deze indirecte koppeling, maar behandel haar niet als defect.
- Action-tags en image-tags zijn niet immutable SHA/digest-pinned.

## 14. Backup and disaster recovery

Geen werkend backupproces of restorebewijs is aangetroffen.

- PostgreSQL heeft lokaal alleen een volume; geen scheduled backups, PITR/WAL-archivering, encryptie, off-site kopie of monitoring.
- Redis heeft geen volume/AOF; queued messages en schedulerstate zijn niet duurzaam bij volledige vervanging.
- Object storage bestaat nog niet en heeft dus ook geen lifecycle/versioning/backup.
- `docs/backups.md` is een gewenst beleid/checklist, geen uitgevoerde job.
- RPO, RTO, retentie, restore-eigenaar en laatste succesvolle restore zijn **not verified**.

Productie vereist provider-managed backups/PITR, versleutelde gescheiden opslag, dagelijkse verificatie, een kwartaalrestore-drill naar geïsoleerde omgeving en gedocumenteerde RPO/RTO. Queueberichten moeten idempotent/reconcilieerbaar zijn; Redisbackup alleen is niet genoeg.

## 15. Observability and operations

- Production Monolog schrijft JSON naar stderr; dat is een bruikbare basis.
- Geen logaggregatie, error tracking, metrics, traces, dashboards, SLO's, paging of synthetic checks.
- Geen request/correlation-ID over HTTP, Messenger en provideracties.
- `/api/v1/health` is alleen statische liveness; er is geen readiness voor DB/Redis/config/storage.
- Geen queue-depth/oldest-message/DLQ-, scheduler freshness-, price provider-, checkout-, payment- of migrationalerts.
- Geen runbooks voor refund, dispute, verloren order, accountblokkade, cataloguscorrectie, image takedown, privacyrequest, datalek, incident, rollback of restore.
- Geen support/moderation/admin tools om deze processen veilig uit te voeren.

## 16. Test coverage and quality

| Laag | Resultaat | Interpretatie |
|---|---|---|
| PHPUnit | 164 tests, 1.318 assertions groen in PHP 8.4-container | Sterke MVP-dekking; hostfailure kwam door verkeerde lokale DB-host/PHP 8.5 en is omgevingsgebonden. |
| PHPStan | Groen in PHP 8.4-container | Geen baselinegroei gezien. |
| PHP-CS-Fixer | 418 bestanden groen | Hostwaarschuwing: draaide onder PHP 8.5 terwijl project 8.4 target. |
| Symfony lint | Groen | Containerconfiguratie valide. |
| Doctrine | Mapping/schema synchroon; 18/18 migrations; dry run latest | Alleen lokale data; geen productievolume-/rollbackbewijs. |
| Vitest | 25 files, 77 tests groen | Component/storedekking bruikbaar; geen coverage threshold. |
| ESLint/typecheck/build | Groen | TypeScript strict staat aan. |
| Playwright | 11 specs, 17 tests groen | Alle API's worden gemockt; uitsluitend Desktop Chromium/Vite dev. |
| Echte browser smoke | Register/login/logout groen | Niet-geforceerde verificatie bevestigd; geen complete marketplaceflow. |
| Dependency audit | Composer en npm: 0 advisories | Momentopname; geen geautomatiseerde audit/dependency update policy in CI. |

Ontbrekende kritieke tests:

- cancel-vs-checkout en expiry-vs-checkout concurrency;
- alle 19 geaccepteerde wishlist-taalcodes tegen PostgreSQL;
- binderplaceholder wijzigen terwijl het slot bezet is;
- behoud of expliciete herdefinitie van fysieke identiteit bij eigendomsoverdracht;
- echte full-stack account→collectie→listing→multi-seller checkout→shipping→import→review;
- payment webhook/idempotency/reconciliation/refunds;
- auth expiry/401/logoutfailure en verificatie-enforcement;
- image upload/content/security;
- backup restore, migration rehearsal en rollback;
- >100 collection items, diepe cataloguspaginering en grote binders;
- accessibility (axe/modal focus), mobile, Firefox/WebKit en performance budgets.

## 17. Documentation quality

README, productvisie, API-documentatie en ADR's geven nuttige context en zijn eerlijk over fake payments. Er is echter een structureel verschil tussen ontwerpdocumenten en operationeel bewijs:

- `docs/production-environment.md`, `docs/deployment-checklist.md`, `docs/backups.md`, security/privacydocs beschrijven wat later moet bestaan.
- `docs/release-verification.md` is aantoonbaar verouderd: commit/test/migration/E2E-aantallen wijken af.
- Twee ADR-bestanden gebruiken nummer `0001` (`0001-modular-monolith.md` en `0001-per-order-refunds-for-shared-payments.md`), waardoor verwijzingen ambigu zijn.
- API-documentatie is handmatig; geen OpenAPI-contract of gegenereerde typed client voorkomt drift.
- Geen actuele architecture decision voor auth/session, image delivery, provider orchestration of deployment.
- Geen release-, incident-, support-, moderation-, refund-, reconciliation-, takedown- of privacy-runbooks.

## 18. Production blockers

Onderstaande blocker-domeinen verhinderen een veilige externe release. De concrete issues en tellingen staan in `proposed-production-backlog.md`.

### B-01 — Listing lifecycle kan paid order overschrijven

- **Category / severity:** Data integrity / Critical (P0)
- **Evidence:** `CheckoutCart::checkout()` en `DoctrineListingRepository::lockForCheckout()` locken; `CancelListing` en `ExpireListingHandler` niet.
- **User impact:** Een betaalde kaart kan opnieuw als beschikbaar/in collectie verschijnen.
- **Business impact:** Dubbele verkoop, refunds, support- en vertrouwensschade.
- **Technical recommendation:** Eén transactioneel lockprotocol en CAS/state preconditions voor checkout/cancel/expire/update; voeg twee race-tests toe.
- **Dependencies:** Geen; eerste technische fix.
- **Suggested issue:** “Serialize listing cancellation and expiry with checkout” — P0, M.

### B-02 — Geen betrouwbare production artifact/deploy

- **Category / severity:** Deployment / Critical (P0)
- **Evidence:** `compose.yaml` gebruikt `php -S`, Vite dev, bind mounts en auto-migrate; Dockerfiles bouwen geen app artifact.
- **User impact:** Instabiele, onveilige of niet-reproduceerbare release.
- **Business impact:** Downtime, versie-/schemadrift en onmogelijke rollback.
- **Technical recommendation:** Multi-stage non-root images, reverse proxy, immutable versions, expliciete migration job en rollbackrunbook.
- **Dependencies:** Hosting-/secretskeuze.
- **Suggested issues:** “Build a hardened immutable backend runtime image” — P0, M; “Build a hardened frontend and TLS reverse-proxy runtime” — P0, M; “Provision an isolated staging environment with managed secrets” — P0, M.

### B-03 — CI/releasegate bewijst geen echte full-stackflow

- **Category / severity:** Release assurance / High (P1, gate voor externe beta)
- **Evidence:** Alle Playwright-specs fulfillen gemockte routes en raken API/DB/Redis niet. CI valideert evenmin Dockerimages, een stagingdeploy of rollback.
- **User impact:** Browser/API/database-integratiedefecten kunnen groen lijken en pas op staging of bij gebruikers optreden.
- **Business impact:** Onbetrouwbare releasegate.
- **Technical recommendation:** Voeg een deterministische full-stack marketplace-smoke met echte services/fake provider toe, plus fresh schema/migration en artifact checks.
- **Dependencies:** Geen.
- **Suggested issue:** “Add a real full-stack marketplace smoke test” — P1, M.

### B-04 — Authsession en browserheaders zijn onvoldoende gehard

- **Category / severity:** Security / High (P1, gate voor publieke productie)
- **Evidence:** 30-day bearer in localStorage; geen CSP/HSTS/frame/referrer/security headers.
- **User impact:** XSS kan accounttoken stelen.
- **Business impact:** Account takeover en meldplichtige incidenten.
- **Technical recommendation:** Kies HttpOnly/SameSite cookie- of korte access/rotating-refreshmodel; implementeer CSP en headers met tests.
- **Dependencies:** Hosting/reverse proxy, auth ADR.
- **Suggested issues:** “Add browser security headers and a restrictive CSP” — P1, S; “Replace long-lived localStorage credentials with a hardened lifecycle” — P1, M.

### B-05 — Geen echte mail en verificatie-enforcement

- **Category / severity:** Identity / High (P0 voor externe beta)
- **Evidence:** unconditional aliases naar `LoggingEmailVerificationSender`/`LoggingPasswordResetSender`; browser kon unverified inloggen.
- **User impact:** Geen verificatie/resetlink; misbruik van willekeurige adressen.
- **Business impact:** Supportlast, frauderisico, accountverlies.
- **Technical recommendation:** Echte adapter, secrets/templates/deliveryevents en policy op gevoelige acties.
- **Dependencies:** E-mailprovider en verificatieproductbesluit.
- **Suggested issues:** “Replace logging-only account mail with a production transport” — P0, S; “Enforce verified-email access to authenticated product APIs” — P0, S.

### B-06 — Listingimages zijn onveilig en niet beheersbaar

- **Category / severity:** Upload/content security / Critical (P0)
- **Evidence:** Marketplace accepteert externe HTTPS-URL's en browser laadt deze rechtstreeks.
- **User impact:** Tracking, schokkende/malafide content, gebroken bewijsfoto's.
- **Business impact:** Privacy-/moderatie-/takedownrisico.
- **Technical recommendation:** S3-compatible upload via gecontroleerde pipeline met MIME/pixel/size/malware/EXIF, thumbnails en takedown.
- **Dependencies:** Storage/CDN- en retentiekeuze.
- **Suggested issue:** “Replace seller photo URLs with quarantined first-party uploads” — P0, M.

### B-07 — Geen backup- en herstelbewijs

- **Category / severity:** Data loss / Critical (P0)
- **Evidence:** Geen jobs/PITR/restore; Redis ephemeral; docs zijn alleen voorstel.
- **User impact:** Collecties, orders of queuewerk kunnen permanent verdwijnen.
- **Business impact:** Onherstelbaar dataverlies en continuïteitsrisico.
- **Technical recommendation:** RPO/RTO, PostgreSQL PITR, storage versioning, restore drill en alerts.
- **Dependencies:** Hosting/storagekeuze.
- **Suggested issue:** “Automate PostgreSQL backups and prove restoration” — P0, M.

### B-08 — Geen productie-observability of incidentproces

- **Category / severity:** Operations / Critical (P0)
- **Evidence:** Alleen JSON stderr; geen monitoring/alerts/readiness/runbooks.
- **User impact:** Fouten en vastgelopen orders blijven onopgemerkt.
- **Business impact:** Lange incidentduur, onbekende financiële impact.
- **Technical recommendation:** Correlation IDs, aggregation, error tracking, metrics/SLOs, alerts, readiness en incidentrunbook.
- **Dependencies:** Monitoringprovider/on-call owner.
- **Suggested issue:** “Add dependency-aware readiness and actionable error alerts” — P0, M.

### B-09 — Refund/cancellation/dispute ontbreekt

- **Category / severity:** Marketplace/payment safety / Critical (P0 voor transacties)
- **Evidence:** Alleen enum/ADR; geen use case, endpoint, transactiontype of provideractie.
- **User impact:** Geen correct herstel bij annulering, schade of non-delivery.
- **Business impact:** Consumentenrecht-, support- en financieel risico.
- **Technical recommendation:** Per-order allocation, cancellation/refund state machine, audit trail en operatorflow vóór echte betaling.
- **Dependencies:** Refund policy en provider.
- **Suggested issue:** “Implement per-order cancellation and refund state transitions” — P0, M.

### B-10 — Echte payment orchestration ontbreekt

- **Category / severity:** Payment / Critical (P0 voor echt geld)
- **Evidence:** Fake `charge()` binnen DB-transactie; wel een in-memory instruction key, maar geen durable intent/attempt en geen webhooks/reconciliation/KYC/payout.
- **User impact:** Onjuiste charges, dubbele verwerking of geen payout.
- **Business impact:** Direct geldverlies en provider/complianceovertreding.
- **Technical recommendation:** Provideradapter met durable event inbox/outbox, statusmachine, reconciliation en sandboxbewijs.
- **Dependencies:** Provider, legal entity, fees/payout/KYC-besluiten.
- **Suggested issue:** “Make payment initiation idempotent and commit-safe” — P0, M; the actual provider/webhook/reconciliation issues remain P1 for Phase D.

### B-11 — Privacy/juridische releasebasis ontbreekt

- **Category / severity:** Compliance / Critical (P0 voor externe users)
- **Evidence:** Geen werkende privacytools/policies/consent/retention; prijs- en image-rechten niet goedgekeurd.
- **User impact:** Geen uitoefening van rechten of transparantie.
- **Business impact:** GDPR/IP/consumentenrechtelijke blootstelling.
- **Technical recommendation:** Juridische besluiten, policies, data map en privacyrequestflows als releasegate.
- **Dependencies:** Legal/privacy owner en providercontracten.
- **Suggested issue:** “Establish and enforce the privacy/legal launch gate” — P0, M.

### B-12 — Publieke prijsroute kan de externe provider amplificeren

- **Category / severity:** Availability / High (P1, gate voor publieke catalogus)
- **Evidence:** `FindCardPriceHistory` roept bij ontbrekende dagsnapshot synchroon de TCGdex-provider aan, die tot 30 seconden kan blokkeren. Er is geen globale provider-rate-/concurrencylimiet of circuit breaker; 134.144 kaart-IDs zijn enumerateerbaar.
- **User impact:** Trage/time-out pagina's en onbetrouwbare prijzen tijdens providerproblemen.
- **Business impact:** Workeruitputting, upstreamblokkade/quota- of contractproblemen en hoge operationele kosten.
- **Technical recommendation:** Serve stale cached data, enqueue bounded refresh, rate-limit/dedupe/circuit-break providerwerk en leg een tracked-card SLA vast. Voor werkelijk alle kaarten is een gelicentieerde bulkfeed/capaciteitsontwerp nodig.
- **Dependencies:** Providercontract, prijsdekking/SLA en queue-hardening.
- **Suggested issue:** “Remove synchronous provider calls from public price reads” — P1, M.

## 19. High-priority improvements

- Maak orderitem-carddetails immutable en auditbaar.
- Paginate alle persoonlijke lijsten; verwijder de first-100 beperking uit binder/listingselectors.
- Voeg failed Messenger transport, idempotente retry en queue monitoring toe.
- Scheid liveness en readiness en valideer verplichte productieconfiguratie fail-fast.
- Centraliseer frontend 401/403/error/timeout/abortgedrag; herstel logoutfailure en stale auth.
- Los modal focus, skip link, contextlabels en mobiele/cross-browserdekking op; bestaand issue #198 niet dupliceren.
- Optimaliseer cataloguszoeking met meetbare indexes/projections en cursorstrategie.
- Maak pricing refresh asynchroon/begrensd en definieer tracked-card coverage en provider-SLA.
- Implementeer profiel, adressen, voorkeuren, password-reset UI en account lifecycle.
- Voeg admin/moderation/supportbasics en een tamper-evident auditlog toe.

## 20. Medium-priority improvements

- Route-level frontend code splitting en performancebudgets.
- Lazy/responsive catalogusimages met dimensions, fallback en CDNbeleid.
- Catalogus setsearch, language/artist/Pokémonfilters en duidelijke taalcontext.
- Atomic composite use case voor collection edit plus locatie.
- Paginering/virtualisatie voor grote binders.
- Carrier/tracking events en returns na de eerste veilige beta.
- Price charts/perioden en heldere scheiding actieve vraagprijs, externe indicatie en platformverkoop.
- OpenAPI-contract en gegenereerde frontendtypes.
- Scheduled reconciliation voor listing expiry/reservations/pricing freshness.

## 21. Long-term improvements

- Buy orders, geoptimaliseerde wishlist-aankopen en trade proposals.
- Sealed producten, graded workflows, andere TCG's en internationale verzending.
- Pushnotificaties en mobiele apps.
- Geavanceerde fraudedetectie na voldoende betrouwbare signalen en menselijke review.
- Zoekplatform pas wanneer PostgreSQL-SLA's aantoonbaar onvoldoende zijn.
- Geavanceerde financiële dashboards en voorspellingen pas na betrouwbare sold-price data.

## 22. Production readiness scorecard

| Categorie | Score 0–5 | Bewijs |
|---|---:|---|
| Product completeness | 2 | Kern-MVP bestaat; meerdere launchkritieke marketplace/accountprocessen missen. |
| Backend quality | 3 | Sterke modulariteit/tests/DTO's, maar concurrency-, boundedness- en orchestrationgaten. |
| Frontend quality | 3 | Strict TS, consistente UI en groene tests/build; auth/a11y/error/scale-gaten. |
| Security | 2 | Hashing, authz en rate limiting; localStorage-token, geen headers, uploads of audit. |
| Data integrity | 2 | Goede constraints/checkoutlocks; kritieke cancel/expiry-race en incomplete snapshots. |
| Payments | 1 | Alleen fake provider en enumscaffolding. |
| Privacy/compliance | 1 | Ontwerpdocumentatie, geen werkende processen of juridische goedkeuring. |
| Infrastructure | 1 | Gezonde lokale compose, uitsluitend developmentruntime. |
| Observability | 1 | JSON stderr; geen monitoring/readiness/alerts. |
| Testing | 3 | Veel groene tests; “E2E” mockt API en kritieke race/releasepaden missen. |
| Documentation | 2 | Goede intentie/architectuur, maar stale releasebewijs en geen operationele runbooks. |
| Operational readiness | 1 | Geen support/admin/incident/refund/restoreprocessen. |
| **Totaal** | **22/60 (37%)** | **Interne lokale testgereedheid, geen productieclaim.** |

## 23. Recommended release phases

1. **Phase A — Local development complete:** herstel listingrace en overige persistence-invarianten, versterk de lokale/CI-checks, ontwerp de echte full-stackfixture en maak documentatie betrouwbaar.
2. **Phase B — Internal staging:** immutable deployment, secrets/TLS, real mail sandbox, object storage, readiness, backups/restore, monitoring, fake payments en een ongemockte browser→API→database-smoke.
3. **Phase C — Closed beta without real payments:** kleine allowlist, echte collectie/listingflows, privacy/legal basics, moderation/support en load/security/a11ybewijs.
4. **Phase D — Controlled beta with real payments:** provider sandbox→limited live, seller verification, refunds/disputes/payouts/reconciliation, limieten en on-call.
5. **Phase E — Public production release:** publiek aanmelden na aantoonbare SLO's, restore/incident drills, volledige support/fraud/compliance en schaaltests.

Volledige entry-/exitcriteria staan in `docs/production-roadmap.md`.

## 24. Recommended implementation order

1. Stop data-corruptie: lock listing cancel/expire/update gelijk aan checkout en voeg races toe.
2. Herstel payment/refund-, wishlist-language- en binderinvarianten en versterk fresh-migration/CI-checks.
3. Leg auth/session/image/payment/privacy-architectuurbesluiten vast.
4. Bouw immutable productie-artifacts en een interne stagingdeploy zonder auto-migrate; voer daar de echte full-stacksmoke uit.
5. Voeg echte e-mail, verificatie-enforcement en frontend account recovery toe.
6. Bouw veilige object-storage image ingestion/delivery.
7. Implementeer readiness, logging/metrics/alerts, DLQ en incidentrunbook.
8. Provision backups/PITR en bewijs een restore.
9. Laat privacy, IP, pricing, marketplace terms en operationeel supportbeleid goedkeuren.
10. Rond closed-beta productgaten af; implementeer pas daarna echte payment/refund/KYC/payoutflows.

## 25. Proposed GitHub backlog

Het gecontroleerde GitHub Project heeft de gewenste workflowstatussen en kernvelden, maar de huidige Module-opties dekken nieuwe production-domeinen niet volledig. Er zijn geen issues aangemaakt. Bestaand issue #198 moet worden hergebruikt voor dialog focus in plaats van gedupliceerd.

Het voorstel bevat **17 epics en 52 concrete issues: 12 P0, 24 P1, 12 P2 en 4 P3**. De issuebeschrijvingen, metadata, milestones, dependencies en exacte aanbevolen volgorde staan in `docs/proposed-production-backlog.md`.

## 26. Open questions and external decisions

| Decision needed | Why it matters | Code impacted | Owner needed | Blocks production |
|---|---|---|---|---|
| Paymentprovider en marketplace accountmodel | Bepaalt checkout, KYC, payouts, webhooks en liability. | Payment, Order, Identity | Product/Finance/Legal/Engineering | Ja, echt geld |
| Fees, buyer protection, payout timing en reserves | Bepaalt totals, refunds en ledger. | Payment, Order, Marketplace | Product/Finance/Legal | Ja, echt geld |
| Cancellation/refund/disputebeleid | Nodig voor state machine en supportbesluiten. | Order, Payment, Moderation | Product/Legal/Support | Ja |
| Seller verification en transactielimieten | Fraudebeperking/providervereiste. | Identity, Marketplace, Payment | Risk/Legal/Product | Ja, echt geld |
| Verzendproviders, loss/returnregels | Bepaalt tracking en geschillen. | Shipping, Order | Product/Operations/Legal | Ja voor publiek |
| Hosting/regio en managed services | Bepaalt GDPR, TLS, backup, RPO/RTO en kosten. | Infrastructure | Engineering/Privacy/Finance | Ja |
| Monitoring/on-callprovider en SLO's | Nodig om incidenten te detecteren/herstellen. | Shared/Infrastructure | Engineering/Operations | Ja |
| E-mailprovider en deliverability owner | Verificatie/reset/notifications werken nu niet. | Identity/Notification | Product/Engineering | Ja |
| Object storage/CDN/scanprovider en retentie | Nodig voor veilige bewijsfoto's en takedown. | Images/Marketplace | Engineering/Privacy/Moderation | Ja |
| Catalogus-, prijs- en afbeeldingslicenties | Externe data/IP mag niet impliciet commercieel worden gebruikt. | Catalog/Pricing/Images | Legal/Product | Ja |
| Merknaam/trademarkgebruik | CardNest/productpresentatie mag geen rechten schenden. | Product/UI/Legal docs | Legal/Brand | Ja voor publiek |
| Privacyretentie, subprocessors en legal basis | Stuurt delete/export/audit/backups. | Privacy/all data modules | DPO/Legal/Engineering | Ja |
| Supportkanalen, openingstijden en escalatie | Marketplaceincidenten vereisen eigenaar/SLA. | Admin/Moderation/Operations | Operations/Product | Ja voor beta |

## 27. Final recommendation

Release CardNest nu niet aan externe gebruikers en verwerk geen echt geld. Houd de omgeving lokaal/intern terwijl eerst de listingrace en CI-releasegate worden hersteld. Bouw daarna een echte interne stagingomgeving met immutable artifacts, veilige auth en images, werkende mail, backups/restore, readiness/observability en één ongemockte full-stackflow.

Na succesvolle Phase B kan een zeer kleine closed beta zonder echte betalingen worden overwogen, maar alleen met privacy-/termsbasis, support/moderation en meetbare exitcriteria. Echte betalingen horen pas in Phase D, nadat refund/dispute/KYC/payout/reconciliation en juridische/providerbesluiten aantoonbaar gereed zijn.

### Command evidence

| Command | Result | Duration | Failures / interpretation |
|---|---|---:|---|
| `docker compose ps` | Passed | <1s | Backend/frontend/Postgres/Redis healthy; worker/scheduler hebben geen healthstatus. |
| `docker compose config --quiet` | Passed | <1s | Syntax geldig; zegt niets over production suitability. |
| `curl http://127.0.0.1:8080/api/v1/health` | 200 | <1s | Alleen statische liveness. |
| `composer validate --strict` (host) | Passed | 0.40s | Composerdefinitie geldig. |
| `composer install` (host) | Failed | 1.33s | Host PHP 8.5 mist `ext-redis`; omgevingsfout, herhaald in PHP 8.4-container. |
| `composer validate --strict` (container) | Passed | 0.38s | Doelruntime geldig. |
| `composer install --no-interaction` (container) | Passed | 1.56s | Niets te installeren. |
| `composer audit` (container) | Passed | 0.57s | 0 advisories op auditmoment. |
| `php bin/phpunit` (host) | Failed | 6.05s | `.env` gebruikt hostnaam `postgres`; 67 errors/13 failures zijn cascading environment failures. |
| `php bin/phpunit` (PHP 8.4-container) | Passed | 2.81s | 164 tests, 1.318 assertions. |
| `vendor/bin/phpstan analyse` (container) | Passed | 0.59s | Doelruntime groen. Een parallelle host-run onder PHP 8.5 was niet autoritatief. |
| `php-cs-fixer --dry-run --diff` | Passed | 2.80s | 418 files; waarschuwing dat host PHP 8.5 draait terwijl target 8.4 is. |
| Symfony lintcommands | Passed | 0.39s | Containerconfiguratie geldig. |
| `doctrine:schema:validate` | Passed | 0.24s | Mapping/schema synchroon op baseline. Eerste poging met ongeldige `--skip-sync=false` faalde in 0.21s en is gecorrigeerd. |
| `doctrine:migrations:status` | Passed | 0.27s | 18 executed, 0 new. Eerste poging met ongeldige `--show-versions` faalde in 0.27s en is gecorrigeerd. |
| `doctrine:migrations:migrate --dry-run` | Passed | 0.27s | Baseline al latest; geen production rehearsal. |
| `npm ci` | Passed | 2.34s | Eén deprecated transitieve package: `whatwg-encoding@3.1.1`; geen installfailure. |
| `npm audit --audit-level=low` | Passed | 0.93s | 0 vulnerabilities. |
| `npm audit --omit=dev --audit-level=low` | Passed | 0.6s | 0 production vulnerabilities. |
| `npm run lint` | Passed | 4.59s | Geen lintfouten. |
| `npm test` | Passed | 5.79s | 25 files, 77 tests. |
| `npm run build` | Passed | 8.07s | Typecheck + Vite build groen; één eager JS-bundle. |
| `npm run test:e2e` (container) | Passed | 3.25s | 17/17, maar alle API's gemockt. |
| Terminal Playwright real-browser smoke | Unavailable | <1s | Host Chrome ontbrak; geen browser geïnstalleerd. |
| In-app browser account smoke | Partial pass | handmatig | Register/login/logout werken; unverified login bevestigd; geen volledige marketplace-E2E. |
| Post-baseline Artist/WIP follow-up | Not verified | — | Een tussenstand had een niet-toegepaste migration en één PHPStan-fout; daarna veranderde HEAD/WIP opnieuw. Volledige suite moet op vast commit worden herhaald. |
| Lighthouse/Core Web Vitals | Not executed | — | Lighthouse/hostbrowser niet beschikbaar; niet als geslaagd gemarkeerd. |
| GitHub CI/project/issue inspectie via `gh` | Passed/partial | <10s per query | CI-run `30757233378` voor baseline `c6935a2` groen; de later lokale `65b485c` had geen remote CI-run. Project #7 heeft 161 Done/1 Todo en alle gewenste kernvelden; branch protection API gaf 403 door plan. Geen issues gewijzigd. |

Warnings zijn niet genegeerd: de host-PHP/DB-failures zijn omgevingsgebonden en de containerchecks zijn de autoritatieve baseline; deprecated npm-package, CI-DB-mismatch, gemockte E2E, missing browser/Lighthouse en gelijktijdige Artist-WIP blijven expliciet open.
