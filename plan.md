# Plan: VideoObject-skjema-støtte for innebygde Vimeo-videoer

## Bakgrunn

Google kan ikke automatisk lese eller indeksere innhold fra Vimeo-iframes. For at Google skal forstå at siden inneholder en video – og for å oppnå video-rike utdrag (rich results) i søk – kreves det et `VideoObject`-skjema i JSON-LD-format med strukturert metadata om videoen.

Pluginet har i dag ingen støtte for video-skjema. Planen er å legge til:
1. **VideoObject JSON-LD-output** i `class-schema.php`
2. **Videofelt i meta-boxen** i `meta-box.php` (Vimeo/YouTube-innbyggingslenke, tittel, beskrivelse, miniatyrbilde-URL, opplastingsdato, varighet)
3. **Lagring av videofelt** i `save_meta_box()` i `meta-box.php`

---

## Endringsplan

### Fil 1: `ai-seo/includes/class-schema.php`

**Endring A – `output_schema()`:** Kall `output_video_schema()` som *tillegg* (ikke erstatning) etter at Article/FAQ/HowTo er outputtet, når videometa finnes.

```php
// Legg til etter eksisterende schema-output i output_schema():
$this->output_video_schema( $post );
```

**Endring B – Ny metode `output_video_schema()`:**

```php
private function output_video_schema( $post ) {
    $post_id   = $post->ID;
    $embed_url = get_post_meta( $post_id, '_ai_seo_video_embed_url', true );

    if ( empty( $embed_url ) ) {
        return;
    }

    $meta_title       = get_post_meta( $post_id, '_ai_seo_meta_title', true );
    $meta_description = get_post_meta( $post_id, '_ai_seo_meta_description', true );

    $video_name = get_post_meta( $post_id, '_ai_seo_video_name', true );
    $video_desc = get_post_meta( $post_id, '_ai_seo_video_description', true );
    $thumbnail  = get_post_meta( $post_id, '_ai_seo_video_thumbnail_url', true );
    $upload_date = get_post_meta( $post_id, '_ai_seo_video_upload_date', true );
    $duration   = get_post_meta( $post_id, '_ai_seo_video_duration', true );

    $name = $video_name
        ? $video_name
        : ( $meta_title ? $meta_title : get_the_title( $post_id ) );

    $description = $video_desc
        ? $video_desc
        : ( $meta_description ? $meta_description : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' ) );

    $schema = array(
        '@context'    => 'https://schema.org',
        '@type'       => 'VideoObject',
        'name'        => $name,
        'description' => $description,
        'embedUrl'    => esc_url_raw( $embed_url ),
        'url'         => get_permalink( $post_id ),
    );

    if ( $thumbnail ) {
        $schema['thumbnailUrl'] = esc_url_raw( $thumbnail );
    }

    if ( $upload_date ) {
        $schema['uploadDate'] = sanitize_text_field( $upload_date );
    }

    // ISO 8601-varighet (f.eks. PT1M30S) – bruker lagret verdi direkte
    if ( $duration ) {
        $schema['duration'] = sanitize_text_field( $duration );
    }

    $this->render_json_ld( $schema );
}
```

---

### Fil 2: `ai-seo/admin/meta-box.php`

**Endring A – `render_meta_box()`:** Les nye videometa-felter og legg til et nytt UI-panel «Video (VideoObject-skjema)» rett etter Schema-type-dropdown.

Nye meta-nøkler som hentes:
```php
$video_embed_url      = get_post_meta( $post->ID, '_ai_seo_video_embed_url', true );
$video_name           = get_post_meta( $post->ID, '_ai_seo_video_name', true );
$video_description    = get_post_meta( $post->ID, '_ai_seo_video_description', true );
$video_thumbnail_url  = get_post_meta( $post->ID, '_ai_seo_video_thumbnail_url', true );
$video_upload_date    = get_post_meta( $post->ID, '_ai_seo_video_upload_date', true );
$video_duration       = get_post_meta( $post->ID, '_ai_seo_video_duration', true );
```

Nytt UI-panel (legges inn etter `<!-- Schema Type -->`-blokken):
```html
<!-- Video Schema -->
<div class="ai-seo-field">
    <label for="ai_seo_video_embed_url">
        <strong>Video – innbyggingslenke (Vimeo/YouTube)</strong>
    </label>
    <input type="url"
           id="ai_seo_video_embed_url"
           name="ai_seo_video_embed_url"
           value="<?php echo esc_attr( $video_embed_url ); ?>"
           class="large-text"
           placeholder="https://player.vimeo.com/video/123456789" />
    <p class="description">Legg inn embed-URL for å aktivere VideoObject-skjema. Eksempel: <code>https://player.vimeo.com/video/123456789</code></p>
</div>
<div id="ai-seo-video-fields" <?php echo $video_embed_url ? '' : 'style="display:none;"'; ?>>
    <div class="ai-seo-field">
        <label for="ai_seo_video_name">Videotittel <span class="description">(valgfri, bruker SEO-tittel hvis tom)</span></label>
        <input type="text" id="ai_seo_video_name" name="ai_seo_video_name"
               value="<?php echo esc_attr( $video_name ); ?>" class="large-text" />
    </div>
    <div class="ai-seo-field">
        <label for="ai_seo_video_description">Videobeskrivelse <span class="description">(valgfri, bruker metabeskrivelse hvis tom)</span></label>
        <textarea id="ai_seo_video_description" name="ai_seo_video_description"
                  class="large-text" rows="2"><?php echo esc_textarea( $video_description ); ?></textarea>
    </div>
    <div class="ai-seo-field">
        <label for="ai_seo_video_thumbnail_url">Miniatyrbilde-URL</label>
        <input type="url" id="ai_seo_video_thumbnail_url" name="ai_seo_video_thumbnail_url"
               value="<?php echo esc_attr( $video_thumbnail_url ); ?>" class="large-text"
               placeholder="https://i.vimeocdn.com/video/..." />
        <p class="description">Direkte URL til videominiatyrbilde (anbefalt: 1280x720). Hent fra Vimeo API eller last opp som mediefil.</p>
    </div>
    <div class="ai-seo-field">
        <label for="ai_seo_video_upload_date">Opplastingsdato</label>
        <input type="date" id="ai_seo_video_upload_date" name="ai_seo_video_upload_date"
               value="<?php echo esc_attr( $video_upload_date ); ?>" />
        <p class="description">Dato videoen ble lastet opp (ikke nødvendigvis publiseringsdato for innlegget).</p>
    </div>
    <div class="ai-seo-field">
        <label for="ai_seo_video_duration">Varighet (ISO 8601)</label>
        <input type="text" id="ai_seo_video_duration" name="ai_seo_video_duration"
               value="<?php echo esc_attr( $video_duration ); ?>" class="regular-text"
               placeholder="PT1M30S" />
        <p class="description">Format: <code>PT[timer]H[minutter]M[sekunder]S</code>. Eksempel: 1 min 30 sek = <code>PT1M30S</code>, 2 timer = <code>PT2H</code>.</p>
    </div>
</div>
```

**Endring B – `save_meta_box()`:** Lagre de nye videofeltene.

```php
// Video Schema-felter.
$video_text_fields = array(
    'ai_seo_video_embed_url'     => '_ai_seo_video_embed_url',
    'ai_seo_video_name'          => '_ai_seo_video_name',
    'ai_seo_video_description'   => '_ai_seo_video_description',
    'ai_seo_video_thumbnail_url' => '_ai_seo_video_thumbnail_url',
    'ai_seo_video_upload_date'   => '_ai_seo_video_upload_date',
    'ai_seo_video_duration'      => '_ai_seo_video_duration',
);

foreach ( $video_text_fields as $field_name => $meta_key ) {
    if ( isset( $_POST[ $field_name ] ) ) {
        $value = sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) );
        if ( $value ) {
            update_post_meta( $post_id, $meta_key, $value );
        } else {
            delete_post_meta( $post_id, $meta_key );
        }
    }
}
```

---

## Nye post-meta-nøkler

| Meta-nøkkel | Type | Formål |
|---|---|---|
| `_ai_seo_video_embed_url` | URL | Vimeo/YouTube embed-URL – aktiverer VideoObject |
| `_ai_seo_video_name` | string | Videotittel (valgfri, fallback til SEO-tittel) |
| `_ai_seo_video_description` | string | Videobeskrivelse (valgfri, fallback til metabeskrivelse) |
| `_ai_seo_video_thumbnail_url` | URL | Direkte URL til miniatyrbilde |
| `_ai_seo_video_upload_date` | date (YYYY-MM-DD) | Opplastingsdato |
| `_ai_seo_video_duration` | string (ISO 8601) | Varighet f.eks. PT1M30S |

---

## Filer som endres

1. `ai-seo/includes/class-schema.php` – 2 endringer
2. `ai-seo/admin/meta-box.php` – 2 endringer

---

## Branch og commit

- Branch: `claude/add-videoobject-schema-Ez5RX`
- Commit og push etter implementasjon
