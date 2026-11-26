# Panel único

Este Laravel reúne lo que antes eran tres proyectos:

| Antes | Ahora en este panel |
|---|---|
| `restaurantev2panel` | Clientes (locales), licencias, versiones, descargas, catálogo de productos — solo el **proveedor** (`users.es_admin`) |
| `factura` | Facturación electrónica SUNAT: empresas, series, tokens, comprobantes, notas, PDF — cualquier usuario registrado |
| `well-known` (peru-consult-api) | Consulta de RUC y DNI: `GET /api/v1/consultas/{ruc|dni}/{número}` |

El dominio de facturación sirve todo: los sistemas de escritorio (restaurante, panadería) usan la misma URL para licencia,
actualizaciones, catálogo, consultas y comprobantes.

## Despliegue

```bash
git pull
composer install --no-dev -o
php artisan migrate --force
php artisan versiones:sincronizar      # publica los instaladores que llegaron en distribucion/
php artisan consultas:padron           # primera vez: importa el padrón reducido del RUC (varios minutos)
php artisan catalogo:openfoodfacts     # opcional: carga productos de panadería de Perú desde Open Food Facts
```

El programador (`php artisan schedule:run` cada minuto) reenvía comprobantes pendientes (10 min), sincroniza
`distribucion/` (5 min) y actualiza el padrón (domingos 03:00).

`.env` nuevo: `APP_LOCALE=es`, `APP_TIMEZONE=America/Lima`, `GOOGLE_CLIENT_ID/SECRET` (de `factura`), y opcional
`CONSULTA_DNI_URL` + `CONSULTA_DNI_TOKEN` (proveedor RENIEC de pago; sin él, el DNI solo se encuentra si tiene RUC 10).

## Datos de `factura`

Las tablas se crean con las migraciones, pero **los datos de la base `factura` no se copiaron**. Si ya hay empresas,
certificados y comprobantes reales, hay que trasladarlos (usuarios, empresas, empresa_tokens, series, comprobantes,
comprobante_items y los archivos de `storage/app/facturacion`) antes de apagar el proyecto anterior. Los tokens `fk_...`
siguen valiendo porque se guarda su hash.

## Consulta de RUC: por qué fallaba

1. El servicio anterior compartía un solo cliente HTTP y **acumulaba las cookies** de SUNAT: mandaba sesiones viejas
   junto al `numRnd` de otra, y dos consultas simultáneas se mezclaban. Ahora cada intento usa su propia sesión.
2. SUNAT tiene delante un filtro anti-bots (Cloudflare) que a veces bloquea a los servidores. Por eso la fuente
   principal es el **padrón reducido oficial** importado en la base; la página de SUNAT queda como respaldo.
