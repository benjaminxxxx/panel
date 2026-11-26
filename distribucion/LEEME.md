# Instaladores de los sistemas de escritorio

Cada sistema deja aquí su instalador más reciente al compilarse con `-Publicar`:

```
distribucion/panaderia/Panaderia-Setup-1.0.1.exe
distribucion/panaderia/version.json      {"version", "plataforma", "archivo", "sha256", "notas"}
distribucion/restaurante/...
```

Al hacer `git push` del panel y desplegarlo, `php artisan versiones:sincronizar` (programado cada
5 minutos) registra la versión, la firma con la llave privada del panel y la publica. Los locales la
instalan solos al abrir el sistema. Solo se guarda el instalador más reciente de cada sistema; las
versiones anteriores quedan en `storage/app/versiones`.

Los .exe pesan ~15 MB: si el repositorio crece mucho, activar Git LFS para `distribucion/**/*.exe`.
