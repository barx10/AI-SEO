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
| Title set and correct length (30–60 characters) | 10 |
| Meta description set (120–160 characters) | 10 |
| Content over 300 words | 10 |
| Focus keyword set | 5 |
| Keyword in SEO title | 10 |
| Keyword in meta description | 10 |
| Keyword in first paragraph | 10 |
| Keyword in subheading (H2–H6) | 5 |
| Keyword density 1–3% | 5 |
| Images have alt text | 5 |
| Internal links in content | 5 |
| External links in content | 5 |
| Subheadings used (H2) | 5 |
| Featured image set | 5 |

The score is classified as:

- **Excellent** (80–100) – green
- **Acceptable** (50–79) – yellow
- **Poor** (0–49) – red

The checklist is displayed with green checkmarks and red crosses in the meta box, so you can quickly see what can be improved. Use the **Refresh Score** button to re-analyze the content.

### Robots Meta Directives

Per post/page you can set the following robots directives:

- **noindex** – Prevents search engines from indexing the page
- **nofollow** – Prevents search engines from following links on the page
- **noarchive** – Prevents caching of the page
- **nosnippet** – Prevents display of text snippets in search results

Each directive has a tooltip explaining its purpose. Pages with `noindex` are automatically excluded from the XML sitemap.

### Cornerstone Content

Mark your most important pages as **cornerstone content**. When editing other posts, a list of cornerstone pages is displayed with copyable URLs, making it easy to link to them for a better internal linking strategy.

### Schema Type Per Post

For each post you can choose a Schema.org type:

- **Article** (default) – Standard article markup
- **FAQPage** – For FAQ pages. Questions are extracted from H3 headings and answers from the content below
- **HowTo** – For guides/tutorials. Steps are extracted from H3 headings

### Social Image

You can choose a custom social image per post via the WordPress media library. This image is used in OpenGraph and Twitter Card meta tags instead of the featured image. A preview of the selected image is displayed in the meta box.

### Readability Analysis

The plugin analyzes your content and provides a readability score (0–100) based on:

- **Average sentence length** – Ideally 15–20 words per sentence (20 points)
- **Average word length** – Ideally 4–6 characters (15 points)
- **Flesch-Kincaid Reading Ease** – Adapted for Norwegian with reduced syllable coefficient (25 points)
- **Passive voice** – Ideally under 10% of sentences (15 points)
- **Transition words** – Ideally over 30% of sentences (15 points)
- **Long sentences** – Percentage of sentences over 25 words (10 points)

The score is classified as:

- **Good readability** (70–100) – green
- **Fair readability** (40–69) – yellow
- **Poor readability** (0–39) – red

Detailed improvement suggestions are displayed below the score, including:
- Suggestions to shorten sentences
- Reduce passive voice usage
- Add more transition words
- Break up long paragraphs (over 150 words)

Problematic sentences are visually highlighted with explanatory tooltips, making it easy to see which sentences need improvement.

### AI Tools

The meta box contains five AI-powered buttons that send the content to the selected AI provider:

| Button | Function |
|--------|----------|
| **Suggest Focus Keyword** | AI analyzes the content and recommends the best focus keyword to optimize for. |
| **Suggest Title** | AI suggests 3 SEO-optimized titles (30–60 characters). If a focus keyword is entered, it is included in the suggestions. |
| **Generate Meta Description** | AI creates an engaging meta description (max 160 characters) based on the content. The result is automatically filled into the field. |
| **Analyze Keywords** | AI analyzes keyword density, shows the 10 most used words with percentages, and provides 3 concrete improvement suggestions. |
| **Suggest Internal Links** | AI suggests relevant internal links based on the content and existing pages, with recommended anchor text and relevance explanation. |

A spinner is displayed while the AI call is in progress. Requests are rate-limited to 30 per minute per user.

## Post List SEO Columns

In the WordPress post list, the plugin adds three extra columns:

| Column | Description |
|--------|-------------|
| **SEO Title** | Shows the first 40 characters of the SEO title |
| **Meta Description** | Shows the first 60 characters of the meta description |
| **SEO Score** | Visual score badge with color coding (green/yellow/red) |

**Inline Editing:** Click directly on the SEO title or meta description in the post list to edit them without opening the post. Changes are saved via AJAX without page reload. Character limits enforce max 70 characters for title and 160 characters for description.

## Redirects

The redirect module is found under **Tools > Redirects**. Here you can:

- Add 301 (permanent) and 302 (temporary) redirects
- View hit count per redirect
- Delete existing redirects

The module includes automatic detection of:

- **Redirect chains** – Warns when a redirect points to a URL that is itself redirected further (A → B → C)
- **Redirect loops** – Detects circular redirects (A → B → A) that can cause infinite redirect loops

Redirects are stored in a dedicated database table with hit counters and timestamps, and are handled early in the WordPress request flow for fast performance. Results are paginated at 50 per page.

## Breadcrumbs

Breadcrumbs are enabled as a module in the settings. Use the shortcode in your templates or content:

```
[ai_seo_breadcrumbs]
```

Breadcrumbs support:
- Posts (with category hierarchy)
- Pages (with parent page hierarchy)
- Custom post types (with archive link)
- Categories (with parent category hierarchy), tags, and archives
- Search results and 404 pages

Breadcrumbs are fully accessible with ARIA labels. BreadcrumbList JSON-LD structured data is automatically added to `<head>` on all pages (except the front page).

## Migration from Other SEO Plugins

Under **Tools > Migration** you can import SEO data from other plugins:

### Supported Plugins

| Plugin | Migrated Fields |
|--------|----------------|
| **Yoast SEO** | SEO title, meta description, focus keyword, robots meta (noindex/nofollow), social image, cornerstone flag |
| **Rank Math** | SEO title, meta description, focus keyword (first if comma-separated), robots meta (noindex/nofollow/noarchive/nosnippet), social image, pillar content |

### Migration Features

- **Detection Tool** – Scans the database for existing Yoast/Rank Math data before migration
- **Overwrite Option** – Choose whether to overwrite or keep existing AI SEO data
- **Variable Stripping** – Automatically removes Yoast variables (`%%title%%` etc.) and Rank Math variables (`%title%` etc.)
- **Migration Report** – Shows count of migrated and skipped items after completion

## Dashboard Widget

After activation, an **AI SEO – Overview** widget is displayed on the WordPress dashboard with:

- Total number of published posts and pages
- Number of posts missing meta description, SEO title, focus keyword, or featured image (with warning colors)
- Number of cornerstone pages
- The 5 posts with the poorest readability (with direct edit links)
- AI provider and API key configuration status (green if configured, red if missing)

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
- `twitter:card` (summary or summary_large_image depending on whether an image exists)
- `twitter:title`, `twitter:description`, `twitter:image`
- `twitter:site`, `twitter:creator` (if Twitter username is set)

### Schema.org Markup
- **Article** – On single posts with author, date, image, word count, and keywords from categories
- **FAQPage** – When schema type is set to FAQ (questions from H3 headings)
- **HowTo** – When schema type is set to HowTo (steps from H3 headings)
- **Organization / LocalBusiness** – On the front page with business information from settings
- **BreadcrumbList** – Automatically on all pages when breadcrumbs are enabled

### XML Sitemap
The sitemap is organized as a sitemap index with separate sub-sitemaps:

- `/sitemap.xml` – Index file pointing to all sub-sitemaps
- `/sitemap-posts-1.xml` – Posts (1000 per page)
- `/sitemap-pages-1.xml` – Pages (1000 per page)
- `/sitemap-{cpt}-1.xml` – Custom post types
- `/sitemap-categories.xml` – Categories
- `/sitemap-tags.xml` – Tags

Features:
- Includes images with URL, title, and alt text for Google Image indexing
- Transient-based caching (1 hour)
- Automatic cache invalidation on post and taxonomy changes
- Search engine pinging (Google and Bing) on publish
- Exclusion of pages with `noindex` directive
- Support for all public custom post types
- Automatic pagination for large sites

## Getting API Keys

- **Anthropic (Claude):** Create an account at [console.anthropic.com](https://console.anthropic.com) and generate an API key under API Keys.
- **OpenAI:** Create an account at [platform.openai.com](https://platform.openai.com) and generate an API key under API Keys.
- **Google (Gemini):** Create an API key via [Google AI Studio](https://aistudio.google.com/apikey).

## Security

- All AJAX requests are validated with WordPress nonce
- The settings page requires `manage_options` capability (administrator)
- The meta box requires `edit_posts` capability (editor, author)
- Redirects and migration require `manage_options` capability (administrator)
- All user input is sanitized with `sanitize_text_field`, `esc_url_raw`, `sanitize_email`, and `wp_kses_post`
- All output is escaped with `esc_html`, `esc_attr`, `esc_url`
- Database queries use prepared statements
- API keys are encrypted with **Sodium** (`sodium_crypto_secretbox`) before being stored in the database, with XOR fallback if Sodium is unavailable
- API key can also be defined as a constant (`AI_SEO_API_KEY`) in `wp-config.php` – this takes precedence over the database value
- **Rate limiting**: Max 30 AI requests per minute per user

## Requirements

- WordPress 5.0 or newer
- PHP 7.4 or newer (PHP 7.2+ with Sodium extension recommended)
- A valid API key from Anthropic, OpenAI, or Google
