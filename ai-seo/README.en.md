English | **[Norsk](README.md)**

# AI SEO – WordPress Plugin

AI SEO is a WordPress plugin that combines traditional SEO tools with AI-powered content optimization. The plugin supports Anthropic (Claude), OpenAI (GPT-4o), and Google (Gemini) as AI providers.

## Installation

1. Upload the `ai-seo/` folder to `/wp-content/plugins/`
2. Go to **Plugins** in the WordPress admin and activate **AI SEO**
3. Go to **Settings > AI SEO** to configure the plugin

## Configuration

### API Setup

1. Navigate to **Settings > AI SEO**
2. Choose your AI provider:
   - **Claude (Anthropic)** – uses the Claude Sonnet 4.5 model
   - **OpenAI** – uses the GPT-4o model
   - **Google (Gemini)** – uses the Gemini 3 Flash Preview model
3. Select model (automatically filtered based on chosen provider)
4. Paste your API key – it is automatically encrypted with Sodium encryption on save

### Modules

In the settings you can enable or disable individual modules:

| Module | Description |
|--------|-------------|
| **XML Sitemap** | Generates a sitemap index with sub-sitemaps at `/sitemap.xml` |
| **Schema.org Markup** | Adds JSON-LD structured data (Article, FAQ, HowTo, Organization) |
| **OpenGraph / Twitter Cards** | Adds social meta tags including custom social image |
| **Breadcrumbs** | Enables breadcrumb trail via shortcode `[ai_seo_breadcrumbs]` |
| **Redirects** | Enables 301/302 redirect manager |

### Social Media

Under the **Social Media** section you can set:

- **Twitter/X Username** – Used in `twitter:site` and `twitter:creator` meta tags

### Organization / Business

Under the **Organization / Business** section you can enter business information used in Organization/LocalBusiness Schema.org markup on the front page:

- **Type** – Organization, LocalBusiness, Restaurant, Store, MedicalBusiness, LegalService, or FinancialService
- **Phone**, **Email**, **Address** – Contact information displayed in structured data

## Usage

### SEO Fields in the Editor

When editing a post or page, you'll find the **AI SEO** meta box below the content editor. Here you can set:

- **SEO Title** – Custom title for search engines (max 70 characters). Overrides the document title in the `<title>` tag.
- **Meta Description** – The description shown in search results (max 160 characters).
- **Focus Keyword** – The keyword you're optimizing the content for. Used by the SEO checklist and AI tools.

Character counters update in real-time as you type and turn red if you exceed the limit.

### Search Result Preview (SERP)

Below the fields, a live preview shows how your post will appear in Google search results, with title, URL, and description.

### SEO Score & Checklist

The plugin calculates an SEO score (0–100) based on 14 checks:

| Check | Weight |
|-------|--------|
| Title set and correct length (50–70 characters) | 10 |
| Meta description set | 10 |
| Content over 300 words | 10 |
| Focus keyword set | 5 |
| Keyword in SEO title | 10 |
| Keyword in meta description | 8 |
| Keyword in first paragraph | 7 |
| Keyword in subheading | 5 |
| Keyword density 1–3% | 8 |
| Images have alt text | 7 |
| Internal links in content | 7 |
| External links in content | 5 |
| Subheadings used (H2/H3) | 5 |
| Featured image set | 3 |

The checklist is displayed with green checkmarks and red crosses in the meta box, so you can quickly see what can be improved.

### Robots Meta Directives

Per post/page you can set the following robots directives:

- **noindex** – Prevents search engines from indexing the page
- **nofollow** – Prevents search engines from following links on the page
- **noarchive** – Prevents caching of the page
- **nosnippet** – Prevents display of text snippets in search results

Pages with `noindex` are automatically excluded from the XML sitemap.

### Cornerstone Content

Mark your most important pages as **cornerstone content**. When editing other posts, a list of cornerstone pages is displayed with copyable URLs, making it easy to link to them for a better internal linking strategy.

### Schema Type Per Post

For each post you can choose a Schema.org type:

- **Article** (default) – Standard article markup
- **FAQPage** – For FAQ pages. Questions are extracted from H3 headings and answers from the content below
- **HowTo** – For guides/tutorials. Steps are extracted from H3 headings

### Social Image

You can choose a custom social image per post via the WordPress media library. This image is used in OpenGraph and Twitter Card meta tags instead of the featured image.

### Readability Analysis

The plugin analyzes your content and provides a readability score (0–100) based on:

- **Average sentence length** – Ideally 15–20 words per sentence (20 points)
- **Average word length** – Ideally 4–6 characters (15 points)
- **Flesch-Kincaid Reading Ease** – Adapted for Norwegian (25 points)
- **Passive voice** – Ideally under 10% of sentences (15 points)
- **Transition words** – Ideally over 30% of sentences (15 points)
- **Long sentences** – Percentage of sentences over 25 words (10 points)

The score is classified as:

- **Good readability** (70–100) – green
- **Fair readability** (40–69) – yellow
- **Poor readability** (0–39) – red

Detailed improvement suggestions are displayed below the score.

### AI Tools

The meta box contains four AI-powered buttons that send the content to the selected AI provider:

| Button | Function |
|--------|----------|
| **Generate Meta Description** | AI creates an engaging meta description (max 160 characters) based on the content. The result is automatically filled into the field. |
| **Suggest Title** | AI suggests 3 SEO-optimized titles. If a focus keyword is entered, it is included in the suggestions. |
| **Analyze Keywords** | AI analyzes keyword density, shows the 10 most used words with percentages, and provides 3 concrete improvement suggestions. |
| **Suggest Internal Links** | AI suggests relevant internal links based on the content and existing pages, with recommended anchor text. |

A spinner is displayed while the AI call is in progress. Requests are rate-limited to 30 per minute per user.

## Redirects

The redirect module is found under **Tools > Redirects**. Here you can:

- Add 301 (permanent) and 302 (temporary) redirects
- View hit count per redirect
- Delete existing redirects

Redirects are stored in a dedicated database table and are handled early in the WordPress request flow.

## Breadcrumbs

Breadcrumbs are enabled as a module in the settings. Use the shortcode in your templates or content:

```
[ai_seo_breadcrumbs]
```

Breadcrumbs support:
- Posts (with category hierarchy)
- Pages (with parent page hierarchy)
- Custom post types
- Categories, tags, and archives
- Search results and 404 pages

BreadcrumbList JSON-LD structured data is automatically added to `<head>` on all pages (except the front page).

## Dashboard Widget

After activation, an **AI SEO – Overview** widget is displayed on the WordPress dashboard with:

- Number of posts missing meta description, SEO title, focus keyword, or featured image
- Number of cornerstone pages
- The 5 posts with the poorest readability
- AI provider and API key configuration status

## Automatic Features

These features require no manual action – they work automatically:

### Canonical URLs
A `<link rel="canonical">` is automatically added to `<head>` on all posts and pages to prevent duplicate content.

### OpenGraph Meta Tags
The following meta tags are added to `<head>`:
- `og:title`, `og:description`, `og:url`, `og:type`
- `og:site_name`, `og:locale`
- `og:image` with dimensions (custom social image or featured image)
- `article:published_time`, `article:modified_time`, `article:tag`, `article:section` (on posts)

### Twitter Cards
- `twitter:card` (summary or summary_large_image)
- `twitter:title`, `twitter:description`, `twitter:image`
- `twitter:site`, `twitter:creator` (if Twitter username is set)

### Schema.org Markup
- **Article** – On single posts with author, date, image, and word count
- **FAQPage** – When schema type is set to FAQ
- **HowTo** – When schema type is set to HowTo
- **Organization / LocalBusiness** – On the front page with business information from settings
- **BreadcrumbList** – Automatically on all pages when breadcrumbs are enabled

### XML Sitemap
The sitemap is organized as a sitemap index with separate sub-sitemaps:

- `/sitemap.xml` – Index file pointing to all sub-sitemaps
- `/sitemap-post-1.xml` – Posts (1000 per page)
- `/sitemap-page-1.xml` – Pages (1000 per page)
- `/sitemap-{cpt}-1.xml` – Custom post types
- `/sitemap-category-1.xml` – Categories
- `/sitemap-post_tag-1.xml` – Tags

Features:
- Transient-based caching (1 hour)
- Automatic cache invalidation on post and taxonomy changes
- Search engine pinging (Google and Bing) on publish
- Exclusion of pages with `noindex` directive
- Support for all public custom post types

## Getting API Keys

- **Anthropic (Claude):** Create an account at [console.anthropic.com](https://console.anthropic.com) and generate an API key under API Keys.
- **OpenAI:** Create an account at [platform.openai.com](https://platform.openai.com) and generate an API key under API Keys.
- **Google (Gemini):** Create an API key via [Google AI Studio](https://aistudio.google.com/apikey).

## Security

- All AJAX requests are validated with WordPress nonce
- The settings page requires `manage_options` capability (administrator)
- The meta box requires `edit_posts` capability
- All user input is sanitized with `sanitize_text_field` and `wp_kses_post`
- API keys are encrypted with **Sodium** (`sodium_crypto_secretbox`) before being stored in the database, with XOR fallback if Sodium is unavailable
- **Rate limiting**: Max 30 AI requests per minute per user
- Redirect actions are protected with nonce verification

## Requirements

- WordPress 5.0 or newer
- PHP 7.4 or newer (PHP 7.2+ with Sodium extension recommended)
- A valid API key from Anthropic, OpenAI, or Google
