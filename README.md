**[English](README.en.md)** | Norsk

# AI SEO – WordPress-programtillegg

AI SEO er et WordPress-programtillegg som kombinerer tradisjonelle SEO-verktøy med AI-drevet innholdsoptimalisering. Pluginet støtter Anthropic (Claude), OpenAI (GPT-4o) og Google (Gemini) som AI-leverandører.

## Installasjon

1. Last opp mappen `ai-seo/` til `/wp-content/plugins/`
2. Gå til **Programtillegg** i WordPress-admin og aktiver **AI SEO**
3. Gå til **Innstillinger > AI SEO** for å konfigurere pluginet

## Konfigurasjon

### API-oppsett

1. Naviger til **Innstillinger > AI SEO**
2. Velg AI-leverandør:
   - **Claude (Anthropic)** – bruker modellen Claude Sonnet 4.5
   - **OpenAI** – bruker modellen GPT-4o
   - **Google (Gemini)** – bruker modellen Gemini 3 Flash Preview
3. Velg modell (filtreres automatisk basert på valgt leverandør)
4. Lim inn API-nøkkelen din – den krypteres automatisk med Sodium-kryptering ved lagring

### Moduler

Under innstillingene kan du aktivere eller deaktivere individuelle moduler:

| Modul | Beskrivelse |
|-------|-------------|
| **XML-sitemap** | Genererer en sitemap-indeks med sub-sitemaps på `/sitemap.xml` |
| **Schema.org-markering** | Legger til JSON-LD-strukturdata (Article, FAQ, HowTo, Organization) |
| **OpenGraph / Twitter Cards** | Legger til sosiale metatagger inkludert egendefinert sosialt bilde |
| **Brødsmuler** | Aktiverer brødsmulesti via shortcode `[ai_seo_breadcrumbs]` |
| **Omdirigeringer** | Aktiverer 301/302 omdirigeringsbehandler |

### Sosiale medier

Under **Sosiale medier**-seksjonen kan du angi:

- **Twitter/X-brukernavn** – Brukes i `twitter:site` og `twitter:creator`-metatagger

### Organisasjon / Bedrift

Under **Organisasjon / Bedrift**-seksjonen kan du angi bedriftsinformasjon som brukes i Organization/LocalBusiness Schema.org-markering på forsiden:

- **Type** – Organization, LocalBusiness, Restaurant, Store, MedicalBusiness, LegalService eller FinancialService
- **Telefon**, **E-post**, **Adresse** – Kontaktinformasjon som vises i strukturdata

## Bruk

### SEO-felter i redigeringssiden

Når du redigerer et innlegg eller en side, finner du meta-boksen **AI SEO** under innholdsfeltet. Her kan du:

- **SEO-tittel** – Egendefinert tittel for søkemotorer (maks 70 tegn). Overskriver dokumenttittelen i `<title>`-taggen.
- **Metabeskrivelse** – Beskrivelsen som vises i søkeresultatene (maks 160 tegn).
- **Fokus-søkeord** – Søkeordet du optimaliserer innholdet for. Brukes av SEO-sjekklisten og AI-verktøyene.

Tegntellere viser sanntidsoppdatering mens du skriver, og markeres rødt hvis du overskrider grensen.

### Forhåndsvisning i søkeresultat (SERP)

Under feltene vises en live forhåndsvisning av hvordan innlegget vil se ut i Google-søkeresultater, med tittel, URL og beskrivelse.

### SEO-poengsum og sjekkliste

Pluginet beregner en SEO-poengsum (0–100) basert på 14 kontroller:

| Kontroll | Vekt |
|----------|------|
| Tittel satt og riktig lengde (30–60 tegn) | 10 |
| Metabeskrivelse satt (120–160 tegn) | 10 |
| Innhold over 300 ord | 10 |
| Fokus-søkeord satt | 5 |
| Søkeord i SEO-tittel | 10 |
| Søkeord i metabeskrivelse | 10 |
| Søkeord i første avsnitt | 10 |
| Søkeord i underoverskrift (H2–H6) | 5 |
| Søkeordtetthet 1–3 % | 5 |
| Bilder har alt-tekst | 5 |
| Interne lenker i innholdet | 5 |
| Eksterne lenker i innholdet | 5 |
| Underoverskrifter brukt (H2) | 5 |
| Fremhevet bilde satt | 5 |

Poengsummen klassifiseres som:

- **Utmerket** (80–100) – grønn
- **Akseptabel** (50–79) – gul
- **Dårlig** (0–49) – rød

Sjekklisten vises med grønne haker og røde kryss i meta-boksen, slik at du raskt ser hva som kan forbedres. Bruk **Oppdater poengsum**-knappen for å analysere innholdet på nytt.

### Robots-metadirektiver

Per innlegg/side kan du sette følgende robots-direktiver:

- **noindex** – Hindrer søkemotorer fra å indeksere siden
- **nofollow** – Hindrer søkemotorer fra å følge lenker på siden
- **noarchive** – Hindrer caching av siden
- **nosnippet** – Hindrer visning av tekstutdrag i søkeresultatene

Hvert direktiv har et verktøytips som forklarer hva det gjør. Sider med `noindex` ekskluderes automatisk fra XML-sitemapen.

### Cornerstone-innhold

Merk de viktigste sidene dine som **cornerstone-innhold**. Når du redigerer andre innlegg, vises en liste over cornerstone-sider med kopierbare URL-er, slik at du enkelt kan lenke til dem for bedre intern lenkestrategi.

### Schema-type per innlegg

For hvert innlegg kan du velge Schema.org-type:

- **Article** (standard) – Standard artikkelmarkering
- **FAQPage** – For FAQ-sider. Spørsmål hentes fra H3-overskrifter og svar fra innholdet under
- **HowTo** – For veiledninger. Trinn hentes fra H3-overskrifter

### Sosialt bilde

Du kan velge et egendefinert sosialt bilde per innlegg via WordPress sitt mediebibliotek. Dette bildet brukes i OpenGraph- og Twitter Card-metatagger i stedet for det fremhevede bildet. En forhåndsvisning av det valgte bildet vises i meta-boksen.

### Lesbarhetsanalyse

Pluginet analyserer innholdet ditt og gir en lesbarhetspoengsum (0–100) basert på:

- **Gjennomsnittlig setningslengde** – Ideelt 15–20 ord per setning (20 poeng)
- **Gjennomsnittlig ordlengde** – Ideelt 4–6 tegn (15 poeng)
- **Flesch-Kincaid lesbarhetsindeks** – Tilpasset norsk med redusert stavelsekoeffisient (25 poeng)
- **Passiv stemme** – Ideelt under 10 % av setningene (15 poeng)
- **Overgangsord** – Ideelt over 30 % av setningene (15 poeng)
- **Lange setninger** – Andelen setninger over 25 ord (10 poeng)

Poengsummen klassifiseres som:

- **God lesbarhet** (70–100) – grønn
- **Middels lesbarhet** (40–69) – gul
- **Dårlig lesbarhet** (0–39) – rød

Detaljerte forbedringsforslag vises under poengsummen, inkludert:
- Forslag om å forkorte setninger
- Redusere bruk av passiv stemme
- Legge til flere overgangsord
- Bryte opp lange avsnitt (over 150 ord)

Problematiske setninger markeres visuelt med forklarende verktøytips, slik at du enkelt ser hvilke setninger som bør forbedres.

### AI-verktøy

Meta-boksen inneholder fem AI-drevne knapper som sender innholdet til den valgte AI-leverandøren:

| Knapp | Funksjon |
|-------|----------|
| **Foreslå fokusord** | AI analyserer innholdet og anbefaler det beste fokus-søkeordet å optimalisere for. |
| **Foreslå tittel** | AI foreslår 3 SEO-optimaliserte titler (30–60 tegn). Hvis fokus-søkeord er fylt inn, inkluderes det i forslagene. |
| **Generer metabeskrivelse** | AI lager en engasjerende metabeskrivelse (maks 160 tegn) basert på innholdet. Resultatet fylles automatisk inn i feltet. |
| **Analyser søkeord** | AI analyserer søkeordtettheten, viser de 10 mest brukte ordene med prosentandel, og gir 3 konkrete forbedringsforslag. |
| **Foreslå interne lenker** | AI foreslår relevante interne lenker basert på innholdet og eksisterende sider, med anbefalt ankertekst og forklaring av relevans. |

En spinner vises mens AI-kallet pågår. Forespørsler er hastighetsbegrenset til 30 per minutt per bruker.

## Innleggsliste med SEO-kolonner

I WordPress sin innleggsliste legger pluginet til tre ekstra kolonner:

| Kolonne | Beskrivelse |
|---------|-------------|
| **SEO-tittel** | Viser de første 40 tegnene av SEO-tittelen |
| **Metabeskrivelse** | Viser de første 60 tegnene av metabeskrivelsen |
| **SEO-poengsum** | Visuell poengsum med fargekoding (grønn/gul/rød) |

**Inline-redigering:** Klikk direkte på SEO-tittel eller metabeskrivelse i innleggslisten for å redigere dem uten å åpne innlegget. Endringer lagres via AJAX uten sidelasting. Tegntellere håndhever maks 70 tegn for tittel og 160 tegn for beskrivelse.

## Omdirigeringer

Omdirigeringsmodulen finner du under **Verktøy > Omdirigeringer**. Her kan du:

- Legge til 301 (permanent) og 302 (midlertidig) omdirigeringer
- Se antall treff (hits) per omdirigering
- Slette eksisterende omdirigeringer

Modulen inkluderer automatisk deteksjon av:

- **Omdirigeringskjeder** – Varsler når en omdirigering peker til en URL som selv omdirigeres videre (A → B → C)
- **Omdirigeringsløkker** – Oppdager sirkulære omdirigeringer (A → B → A) som kan forårsake uendelig omdirigeringsløkker

Omdirigeringer lagres i en egen databasetabell med treffeller og tidsstempler, og håndteres tidlig i WordPress sin forespørselsflyt for rask ytelse. Resultatene pagineres med 50 per side.

## Brødsmuler

Brødsmuler aktiveres som modul i innstillingene. Bruk shortcoden i dine maler eller innhold:

```
[ai_seo_breadcrumbs]
```

Brødsmulene støtter:
- Innlegg (med kategori-hierarki)
- Sider (med foreldreside-hierarki)
- Egendefinerte innholdstyper (med arkivlenke)
- Kategorier (med foreldrekategori-hierarki), stikkord og arkiver
- Søkeresultater og 404-sider

Brødsmulene er fullt tilgjengelige med ARIA-merking. BreadcrumbList JSON-LD-strukturdata legges automatisk til i `<head>` på alle sider (unntatt forsiden).

## Migrering fra andre SEO-plugins

Under **Verktøy > Migrering** kan du importere SEO-data fra andre plugins:

### Støttede plugins

| Plugin | Migrerte felt |
|--------|---------------|
| **Yoast SEO** | SEO-tittel, metabeskrivelse, fokus-søkeord, robots-meta (noindex/nofollow), sosialt bilde, cornerstone-flagg |
| **Rank Math** | SEO-tittel, metabeskrivelse, fokus-søkeord (første hvis kommaseparert), robots-meta (noindex/nofollow/noarchive/nosnippet), sosialt bilde, pilarinnhold |

### Migreringsfunksjoner

- **Deteksjonsverktøy** – Skanner databasen for eksisterende Yoast/Rank Math-data før migrering
- **Overskriv-valg** – Velg om eksisterende AI SEO-data skal overskrives eller beholdes
- **Variabelrensing** – Fjerner automatisk Yoast-variabler (`%%title%%` osv.) og Rank Math-variabler (`%title%` osv.)
- **Migreringsrapport** – Viser antall migrerte og hoppede elementer etter fullført import

## Dashboard-widget

Etter aktivering vises en **AI SEO – Oversikt**-widget på WordPress-dashbordet med:

- Totalt antall publiserte innlegg og sider
- Antall innlegg som mangler metabeskrivelse, SEO-tittel, fokus-søkeord eller fremhevet bilde (med varselfarge)
- Antall cornerstone-sider
- De 5 innleggene med dårligst lesbarhet (med direkte redigeringslenker)
- Status for AI-leverandør og API-nøkkel (grønn hvis konfigurert, rød hvis mangler)

## Automatiske funksjoner

Disse funksjonene krever ingen manuell handling – de fungerer automatisk:

### Kanoniske URL-er
En `<link rel="canonical">` legges automatisk til i `<head>` på alle innlegg og sider for å unngå duplikatinnhold.

### OpenGraph-metatagger
Følgende metatagger legges til i `<head>`:
- `og:title`, `og:description`, `og:url`, `og:type`
- `og:site_name`, `og:locale`
- `og:image` med dimensjoner (egendefinert sosialt bilde eller fremhevet bilde)
- `article:published_time`, `article:modified_time`, `article:tag`, `article:section` (på innlegg)

### Twitter Cards
- `twitter:card` (summary eller summary_large_image avhengig av om bilde finnes)
- `twitter:title`, `twitter:description`, `twitter:image`
- `twitter:site`, `twitter:creator` (hvis Twitter-brukernavn er satt)

### Schema.org-markering
- **Article** – På enkeltinnlegg med forfatter, dato, bilde, ordtelling og nøkkelord fra kategorier
- **FAQPage** – Når schema-type er satt til FAQ (spørsmål fra H3-overskrifter)
- **HowTo** – Når schema-type er satt til HowTo (trinn fra H3-overskrifter)
- **Organization / LocalBusiness** – På forsiden med bedriftsinformasjon fra innstillingene
- **BreadcrumbList** – Automatisk på alle sider når brødsmuler er aktivert

### XML-sitemap
Sitemapen er organisert som en sitemap-indeks med separate sub-sitemaps:

- `/sitemap.xml` – Indeksfil som peker til alle sub-sitemaps
- `/sitemap-posts-1.xml` – Innlegg (1000 per side)
- `/sitemap-pages-1.xml` – Sider (1000 per side)
- `/sitemap-{cpt}-1.xml` – Egendefinerte innholdstyper
- `/sitemap-categories.xml` – Kategorier
- `/sitemap-tags.xml` – Stikkord

Funksjoner:
- Inkluderer bilder med URL, tittel og alt-tekst for Google Bildesøk
- Caching med transients (1 time)
- Automatisk cache-invalidering ved innleggsendringer og taksonomiendringer
- Søkemotorpinging (Google og Bing) ved publisering
- Ekskludering av sider med `noindex`-direktiv
- Støtte for alle offentlige egendefinerte innholdstyper
- Automatisk paginering for store nettsteder

## Skaffe API-nøkler

- **Anthropic (Claude):** Opprett konto på [console.anthropic.com](https://console.anthropic.com) og generer en API-nøkkel under API Keys.
- **OpenAI:** Opprett konto på [platform.openai.com](https://platform.openai.com) og generer en API-nøkkel under API Keys.
- **Google (Gemini):** Opprett en API-nøkkel via [Google AI Studio](https://aistudio.google.com/apikey).

## Sikkerhet

- Alle AJAX-forespørsler valideres med WordPress nonce
- Innstillingssiden krever `manage_options`-tilgang (administrator)
- Meta-boksen krever `edit_posts`-tilgang (redaktør, forfatter)
- Omdirigeringer og migrering krever `manage_options`-tilgang (administrator)
- All brukerinndata saniteres med `sanitize_text_field`, `esc_url_raw`, `sanitize_email` og `wp_kses_post`
- All utdata escapes med `esc_html`, `esc_attr`, `esc_url`
- Databasespørringer bruker prepared statements
- API-nøkkelen krypteres med **Sodium** (`sodium_crypto_secretbox`) før lagring i databasen, med XOR-fallback hvis Sodium ikke er tilgjengelig
- API-nøkkel kan også defineres som konstant (`AI_SEO_API_KEY`) i `wp-config.php` – denne har forrang over databaseverdien
- **Hastighetsbegrensning**: Maks 30 AI-forespørsler per minutt per bruker

## Krav

- WordPress 5.0 eller nyere
- PHP 7.4 eller nyere (PHP 7.2+ med Sodium-utvidelsen anbefales)
- En gyldig API-nøkkel fra Anthropic, OpenAI eller Google
