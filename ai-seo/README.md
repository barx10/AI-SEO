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
4. Lim inn API-nøkkelen din – den krypteres automatisk ved lagring

### Moduler

Under innstillingene kan du aktivere eller deaktivere individuelle moduler:

| Modul | Beskrivelse |
|-------|-------------|
| **XML-sitemap** | Genererer en sitemap tilgjengelig på `/sitemap.xml` |
| **Schema.org-markering** | Legger til Article JSON-LD-strukturdata på innlegg |
| **OpenGraph / Twitter Cards** | Legger til sosiale metatagger i `<head>` |

## Bruk

### SEO-felter i redigeringssiden

Når du redigerer et innlegg eller en side, finner du meta-boksen **AI SEO** under innholdsfeltet. Her kan du:

- **SEO-tittel** – Egendefinert tittel for søkemotorer (maks 70 tegn). Overskriver dokumenttittelen i `<title>`-taggen.
- **Metabeskrivelse** – Beskrivelsen som vises i søkeresultatene (maks 160 tegn).
- **Fokus-søkeord** – Søkeordet du optimaliserer innholdet for. Brukes av AI-verktøyene.

Tegntellere viser sanntidsoppdatering mens du skriver, og markeres rødt hvis du overskrider grensen.

### Forhåndsvisning i søkeresultat (SERP)

Under feltene vises en live forhåndsvisning av hvordan innlegget vil se ut i Google-søkeresultater, med tittel, URL og beskrivelse.

### Lesbarhetsanalyse

Pluginet analyserer innholdet ditt og gir en lesbarhetspoengsum (0–100) basert på:

- Gjennomsnittlig setningslengde (ideelt 15–20 ord)
- Gjennomsnittlig ordlengde (ideelt 4–6 tegn)

Poengsummen klassifiseres som:

- **God lesbarhet** (80–100) – grønn
- **Middels lesbarhet** (50–79) – gul
- **Dårlig lesbarhet** (0–49) – rød, med anbefaling om kortere setninger og enklere ord

### AI-verktøy

Meta-boksen inneholder tre AI-drevne knapper som sender innholdet til den valgte AI-leverandøren:

| Knapp | Funksjon |
|-------|----------|
| **Generer metabeskrivelse** | AI lager en engasjerende metabeskrivelse (maks 160 tegn) basert på innholdet. Resultatet fylles automatisk inn i feltet. |
| **Foreslå tittel** | AI foreslår 3 SEO-optimaliserte titler. Hvis fokus-søkeord er fylt inn, inkluderes det i forslagene. |
| **Analyser søkeord** | AI analyserer søkeordtettheten, viser de 10 mest brukte ordene med prosentandel, og gir 3 konkrete forbedringsforslag. |

En spinner vises mens AI-kallet pågår. Feilmeldinger vises direkte i meta-boksen dersom noe går galt.

## Automatiske funksjoner

Disse funksjonene krever ingen manuell handling – de fungerer automatisk på alle publiserte innlegg og sider:

### Kanoniske URL-er
En `<link rel="canonical">` legges automatisk til i `<head>` på alle innlegg og sider for å unngå duplikatinnhold.

### OpenGraph-metatagger
Følgende metatagger legges til i `<head>`:
- `og:title`, `og:description`, `og:url`, `og:type`
- `og:site_name`, `og:locale`
- `og:image` (hvis innlegget har et fremhevet bilde)
- `article:published_time` og `article:modified_time` (på innlegg)

### Twitter Cards
- `twitter:card` (summary eller summary_large_image)
- `twitter:title`, `twitter:description`, `twitter:image`

### Schema.org Article-markering
På enkeltinnlegg legges det til et `<script type="application/ld+json">`-element med Article-strukturdata, inkludert forfatter, publiseringsdato, bilde og ordtelling.

### XML-sitemap
Når modulen er aktivert, genereres en sitemap på `/sitemap.xml` som inneholder:
- Forsiden
- Alle publiserte innlegg
- Alle publiserte sider
- Alle kategorier med innlegg

> **Merk:** Etter aktivering av pluginet (eller endring av sitemap-innstillingen) kan det være nødvendig å oppdatere permalenker under **Innstillinger > Permalenker** for at `/sitemap.xml` skal fungere.

## Skaffe API-nøkler

- **Anthropic (Claude):** Opprett konto på [console.anthropic.com](https://console.anthropic.com) og generer en API-nøkkel under API Keys.
- **OpenAI:** Opprett konto på [platform.openai.com](https://platform.openai.com) og generer en API-nøkkel under API Keys.
- **Google (Gemini):** Opprett en API-nøkkel via [Google AI Studio](https://aistudio.google.com/apikey).

## Sikkerhet

- Alle AJAX-forespørsler valideres med WordPress nonce
- Innstillingssiden krever `manage_options`-tilgang (administrator)
- Meta-boksen krever `edit_posts`-tilgang
- All brukerinndata saniteres med `sanitize_text_field` og `wp_kses_post`
- API-nøkkelen krypteres før lagring i databasen

## Krav

- WordPress 5.0 eller nyere
- PHP 7.4 eller nyere
- En gyldig API-nøkkel fra Anthropic, OpenAI eller Google
