# API de facturación (v1)

Servicio central que emite facturas y boletas electrónicas SUNAT para todos los sistemas (pos, alquiler, restaurant, hotel).

## Autenticación

Cada sistema usa un token ligado a **una empresa (RUC)** y a **un sistema**:

```
Authorization: Bearer fk_xxxxxxxx
Accept: application/json
```

El dueño de la empresa lo genera en el panel (**Mis empresas → su empresa → Sistemas conectados**) y solo se muestra una vez.
Una empresa configura su certificado una sola vez y usa un token distinto en cada sistema (POS, hotel, etc.).
`GET /api/v1/yo` sirve para verificar a qué empresa y series está vinculado el token.

## Emitir: `POST /api/v1/comprobantes`

```json
{
  "referencia_externa": "venta-1001",
  "tipo_comprobante": "01",
  "serie": "F001",
  "fecha_emision": "2026-09-30T10:15:00-05:00",
  "cliente": {
    "tipo_documento": "6",
    "numero_documento": "20100070970",
    "razon_social": "SUPERMERCADOS PERUANOS S.A.",
    "direccion": "opcional",
    "email": "opcional"
  },
  "items": [
    { "codigo": "MRT-01", "descripcion": "MARTILLO 16OZ", "cantidad": 2, "precio_unitario": "45.90" },
    { "descripcion": "CABLE THW 14", "unidad": "MTR", "cantidad": "25.5", "precio_unitario": "1.80" },
    { "descripcion": "SERVICIO DE CORTE", "unidad": "ZZ", "cantidad": 1, "valor_unitario": "4.2373" }
  ]
}
```

| Campo | Notas |
|---|---|
| `referencia_externa` | Id de la venta en tu sistema. **Obligatorio.** Si reintentas con la misma referencia no se crea otro comprobante: responde `200` con el existente. |
| `tipo_comprobante` | `01` factura, `03` boleta. |
| `serie` | Debe estar registrada para la empresa (`F###` facturas, `B###` boletas). El correlativo lo asigna este servicio. |
| `fecha_emision` | Opcional (por defecto: ahora, hora de Lima). Máximo 3 días de antigüedad, nunca futura. |
| `cliente` | Obligatorio en factura (solo RUC, tipo `6`). En boleta es opcional hasta S/ 700: sin cliente se emite a "CLIENTES VARIOS". |
| `items[].importe` | **Recomendado.** Total de la línea **con IGV** tal como se cobró (monto fijado, con descuentos ya aplicados). La API calcula hacia atrás precio unitario (hasta 10 decimales), valor sin IGV e IGV, y el comprobante suma exactamente lo cobrado. Ej.: 3 unidades por `"100.00"`. |
| `items[].precio_unitario` | Precio unitario **con IGV**. Ojo: `3 × 33.33 = 99.99`; si el monto fijado es 100.00, use `importe`. |
| `items[].valor_unitario` | Precio unitario **sin IGV**. |

Opcionalmente, `informacion_adicional` (hasta 12) agrega datos del negocio a la representación impresa, sin tocar el XML de SUNAT: `[{ "ubicacion": "cabecera|centro|pie", "etiqueta": "Cuenta BCP", "valor": "191-1234567-0-12" }]`. También se acepta en la nota de crédito.

Por ítem se envía **solo uno** de `importe`, `precio_unitario` o `valor_unitario`.
No existe "monto de redondeo": el portal de SUNAT lo agrega porque parte de un precio unitario con pocos decimales; aquí se parte del importe, así que el total siempre coincide.
| `items[].unidad` | Por defecto `NIU`. También `ZZ` (servicio), `KGM`, `MTR`, `LTR`, `BX`, `HUR`, `DAY`, `MON`, etc. |
| `items[].cantidad` | Hasta 6 decimales. |

Envía los montos como texto (`"45.90"`) para evitar errores de redondeo de float en tu lado.

Solo se emite en soles. Por ítem, `afectacion_igv` (catálogo 07): `10` gravado (por defecto), `20` exonerado (Apéndice I: huevos, leche cruda, frutas y verduras frescas; Amazonía), `30` inafecto. En gravados, `porcentaje_igv` es la tasa total: `18` (por defecto; 2026 = 15.5 IGV + 2.5 IPM) o `10.5` (restaurantes y hoteles MYPE, Ley 31556). Las tasas aceptadas están en `config/facturacion.php`. Una venta mixta (pan + huevos) separa op. gravadas y exoneradas.

## Respuesta

- `201`: comprobante creado. `200`: ya existía (misma `referencia_externa`).
- `422`: datos inválidos (`errors` por campo). **No se consume correlativo.**
- `401`: token inválido o revocado.

El resultado ante SUNAT está en `estado`:

| estado | Significado | Qué hacer |
|---|---|---|
| `aceptado` | CDR código 0 | Nada. |
| `observado` | Aceptado con observaciones | Válido. Corregir el dato en siguientes emisiones. |
| `rechazado` | SUNAT lo rechazó (2000-3999) | El comprobante no existe para SUNAT. Emitir uno nuevo corregido. |
| `error_envio` | Sin respuesta válida (red, timeout) | Se reenvía solo cada 10 min con el mismo XML, o `POST /comprobantes/{id}/reenviar`. |
| `pendiente` | Numerado, aún no enviado | Igual que `error_envio`. |

`valido: true` equivale a `aceptado` u `observado`.

## Nota de crédito: `POST /api/v1/comprobantes/{id}/nota-credito`

Anula o corrige una factura o boleta **aceptada** de la empresa. `{id}` es el `id` del comprobante que se modifica.

```json
{
  "referencia_externa": "anulacion-venta-1001",
  "motivo_codigo": "01",
  "motivo": "El cliente desistió de la compra"
}
```

| Campo | Notas |
|---|---|
| `referencia_externa` | **Obligatorio.** Idempotente igual que en la emisión: reintentar no crea otra nota. |
| `motivo_codigo` | Catálogo 09. Totales (sin ítems): `01` anulación de la operación (por defecto), `02` anulación por error en el RUC, `06` devolución total. Parciales (con ítems): `04` descuento global, `05` descuento por ítem, `07` devolución por ítem, `09` disminución en el valor. SUNAT no admite `04` ni `05` en boletas: use `09`. |
| `motivo` | Texto que aparece en la nota (máx. 250). Por defecto, la descripción del código. |
| `serie` | Opcional. Por defecto, la serie de notas de crédito activa de la empresa que empieza con la misma letra del comprobante (`FC01` para facturas, `BC01` para boletas). |
| `fecha_emision` | Opcional (por defecto: ahora). Mismos límites que en la emisión. |
| `items` | Solo con los motivos parciales: lo devuelto o lo rebajado, con el mismo formato que en la emisión (ej. `[{ "descripcion": "REBAJA POR DEMORA", "cantidad": 1, "importe": "10.50" }]`). Con los motivos totales no se envían: la nota copia el cliente, los ítems y el total del comprobante. |

Reglas:

- El comprobante debe estar `aceptado` u `observado`. Uno `rechazado` no existe para SUNAT y no necesita nota.
- Lo acreditado entre todas las notas vigentes de un comprobante nunca supera su total (las rechazadas no cuentan).
- La empresa debe tener registrada una serie de notas de crédito (panel → **Series** → *Nota de crédito*).

La respuesta es la de cualquier comprobante, con `tipo_comprobante: "07"`, `comprobante_afectado` (`id`, `tipo_comprobante`, `numero`) y `motivo` (`codigo`, `descripcion`). Se consulta, reenvía y descarga con los mismos endpoints de abajo.

## Nota de débito: `POST /api/v1/comprobantes/{id}/nota-debito`

Aumenta lo cobrado en una factura o boleta **aceptada** (flete que no se facturó, intereses, penalidad). Siempre lleva `items`: el concepto que se suma.

```json
{
  "referencia_externa": "aumento-venta-1001",
  "motivo_codigo": "02",
  "motivo": "Flete que no se incluyó",
  "items": [{ "descripcion": "FLETE", "cantidad": 1, "importe": "23.60" }]
}
```

`motivo_codigo` es del catálogo 10: `01` intereses por mora, `02` aumento en el valor (por defecto), `03` penalidades. La serie por defecto es la de notas de débito de la empresa que empieza con la letra del comprobante (`FD01`, `BD01`). La respuesta lleva `tipo_comprobante: "08"`.

## Modo prueba y numeración

Cada serie lleva dos contadores: el real (producción) y el de modo prueba (beta). Lo emitido en modo prueba **no gasta** los números reales: al pasar la empresa a producción, el siguiente comprobante sigue al último número real. `GET /yo` devuelve en `ultimo_correlativo` el del entorno en que está la empresa. Un comprobante de prueba no se puede modificar con notas una vez en producción.

## Otros endpoints

- `GET /api/v1/comprobantes/{id}`: estado actual.
- `POST /api/v1/comprobantes/{id}/reenviar`: solo para `pendiente` o `error_envio`.
- `GET /api/v1/comprobantes/{id}/xml`: XML firmado.
- `GET /api/v1/comprobantes/{id}/cdr`: CDR (zip).
- `GET /api/v1/comprobantes/{id}/pdf?formato=a4|ticket`: representación impresa con logo, QR y hash (ticket de 80 mm). En modo prueba lleva la marca "PRUEBA - SIN VALOR". Las URLs vienen en `archivos.pdf` y `archivos.ticket` de la respuesta.
- `archivos.publico`: enlace firmado al PDF A4 que se abre **sin token ni sesión**, para mandarlo al cliente final (WhatsApp, correo). No caduca; cambiar cualquier parte del enlace lo invalida.

## Alta de una empresa

Lo normal es desde el panel web:

1. Crear cuenta (correo con verificación obligatoria, o **Continuar con Google**).
2. **Registrar empresa**: RUC, domicilio fiscal, logo, modo prueba o producción, certificado (.pfx/.p12 con contraseña, o .pem) y, en producción, usuario y clave SOL.
3. **Series**: F001, B001… (si viene de otro sistema, indicar el último número emitido).
4. **Sistemas conectados**: generar un token por sistema y pegarlo en su configuración.

Para soporte o automatización también existen comandos:

```bash
php artisan facturacion:empresa 20123456789 --razon-social="MI EMPRESA SAC" --direccion="AV. X 123" \
  --ubigeo=150101 --departamento=LIMA --provincia=LIMA --distrito=LIMA --entorno=beta
php artisan facturacion:certificado 20123456789 ruta/certificado.pfx --clave=****
php artisan facturacion:serie 20123456789 factura F001
php artisan facturacion:serie 20123456789 boleta B001 --correlativo=1520   # si migras, el último número usado
php artisan facturacion:serie 20123456789 nota_credito FC01                # notas de crédito de facturas
php artisan facturacion:serie 20123456789 nota_credito BC01                # notas de crédito de boletas
php artisan facturacion:serie 20123456789 nota_debito FD01                 # notas de débito de facturas (BD01 para boletas)
php artisan facturacion:token 20123456789 pos --nombre="POS tienda centro"
```

Para pasar a producción: `--entorno=produccion --sol-usuario=... --sol-clave=...` y un certificado real.
Demo inmediata en beta: `php artisan facturacion:demo pos`.

## Pruebas

```bash
php artisan test                                                # unitarias + API (SUNAT simulado)
SUNAT_BETA=1 php vendor/bin/phpunit --testsuite Integration     # envíos reales a SUNAT beta
SUNAT_BETA=1 SUNAT_CERT=cert.pfx SUNAT_CERT_CLAVE=*** php vendor/bin/phpunit --testsuite Integration
```

## Detracción (SPOT)

Solo facturas mayores a S/ 700. Se agrega al emitir:

```json
"detraccion": { "codigo": "025", "porcentaje": "10", "cuenta": "00-123-456789" }
```

`codigo` del catálogo 54 aceptado en `config/facturacion.php` (`001` azúcar, `023` leche, `025` fabricación por encargo,
`035` bienes exonerados, `037` demás servicios gravados). Sin `porcentaje` se usa el del código. La factura sale con
tipo de operación `1001`, la leyenda `2006`, la cuenta del Banco de la Nación y el monto; la respuesta incluye
`detraccion: {codigo, porcentaje, monto, cuenta, medio_pago}`. La venta de pan con insumos propios **no** lleva detracción.

## Consulta de RUC y DNI

```
GET /api/v1/consultas/ruc/20131312955     Authorization: Bearer <token de empresa fk_..., clave de un local o CONSULTA_TOKEN>
GET /api/v1/consultas/dni/48004836
```

`200` datos · `404` no existe · `422` número mal escrito · `503` SUNAT no responde (reintentar). RUC: primero el
**padrón reducido oficial** de SUNAT importado en la base (`php artisan consultas:padron`, semanal), luego la página de
consulta. DNI: proveedor RENIEC configurado (`CONSULTA_DNI_URL`, `CONSULTA_DNI_TOKEN`) o, sin él, solo personas con RUC 10.
También acepta `?token=` como el servicio anterior.
