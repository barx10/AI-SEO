# Design: Auto-redirect ved slugendring

**Dato:** 2026-03-08
**Status:** Godkjent

## Bakgrunn

Ved redesign av nettsted endres permalenker på innlegg med dårlige overskrifter. Pluginen har allerede et manuelt redirect-system (`class-redirects.php` + `redirects-page.php`). Målet er å automatisk opprette 301-redirect når en publisert posts slug endres.

## Tilnærming

Hook på WordPress `post_updated`-action som gir tilgang til både gammel og ny post-tilstand uten ekstra databaselagring.

## Arkitektur

### Ny metode i `AI_SEO_Redirects`

```php
public function maybe_create_redirect_on_slug_change( $post_id, $post_after, $post_before )
```

Registreres via `add_action( 'post_updated', ... )` i `init()`.

### Logikk (i rekkefølge)

1. Hopp over hvis `$post_before->post_status !== 'publish'` — ingen offentlig URL å redirecte fra
2. Hopp over hvis `$post_before->post_name === $post_after->post_name` — slug uendret
3. Hopp over hvis post type ikke er offentlig (`is_post_type_viewable()`)
4. Bygg gammel permalink: klone `$post_before`, sett `post_name` til gammel slug, kall `get_permalink()` på klonet
5. Hent ny permalink: `get_permalink( $post_id )`
6. Ekstraher path fra gammel URL (bare stien, ikke domene)
7. Kall `AI_SEO_Redirects::add( $old_path, $new_url, 301 )` — innebygd duplikatsjekk forhindrer doble redirects

### Kanttilfeller håndtert

- **Ny post (aldri publisert):** `post_before->post_status !== 'publish'` → hoppes over
- **Lagring uten slugendring:** slug-sammenlikning → hoppes over
- **Duplikat source:** `add()` returnerer `WP_Error` som ignoreres (redirect fins fra før)
- **Slug endret tilbake:** oppretter ikke ny redirect (source fins allerede fra første endring)
- **Privat/draft post:** `post_status`-sjekk → hoppes over

## Filer som endres

- `includes/class-redirects.php` — ny metode + hook-registrering i `init()`

Ingen endringer i admin-UI, main plugin-fil, eller JS.

## Testing

- Endre slug på publisert innlegg → redirect opprettes i databasen
- Lagre uten slugendring → ingen ny redirect
- Endre slug på draft → ingen redirect
- Endre slug to ganger → første redirect oppdateres ikke (duplikat ignoreres), men ny redirect fra andre slug opprettes
