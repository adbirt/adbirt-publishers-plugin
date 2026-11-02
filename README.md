# Adbirt Publisher (WordPress plugin)

Shows Adbirt ads in ad slots on a WordPress site, through the Adbirt publisher
script (`https://dsp.adbirt.com/publisher.js`, source in
`adbirt/adbirt-web-sdk`, `packages/publisher`).

- Shortcode `[adbirt_slot id="slot_..."]`, block `adbirt/slot`, widget "Adbirt Ad Slot".
- Settings > Adbirt Publisher: publisher site id (reference only), script URL, ad server override (testing).
- Each slot renders `<ins class="adbirt-slot" data-slot="slot_..." style="display:block"></ins>`;
  the script is enqueued once in the footer, only on pages with a slot.

Sites and slots are created in the Adbirt dashboard (Publisher). Ads are served
only on the verified domain.

Version 2.0.0 replaces the 1.x plugin, whose endpoints on adbirt.com no longer
exist. The 1.x shortcode `[adbirt_ads_display]` now renders nothing (an HTML
comment for editors).

## Checks

```sh
docker run --rm -v "$PWD":/plugin:ro -w /plugin wordpress:php8.3-apache \
  sh -c 'for f in $(find . -name "*.php" -not -path "./.git/*"); do php -l "$f"; done'
```
