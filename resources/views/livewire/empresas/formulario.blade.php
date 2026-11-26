<section class="mx-auto w-full max-w-3xl">
    <div class="mb-6">
        <flux:heading size="xl" level="1">{{ $empresaId ? 'Editar empresa' : 'Registrar empresa' }}</flux:heading>
        <flux:subheading>Datos fiscales tal como figuran en su Ficha RUC. Se imprimen en cada comprobante.</flux:subheading>
    </div>

    <form wire:submit="guardar" class="space-y-8">
        {{-- 1. Datos fiscales --}}
        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                @if ($empresaId)
                    <flux:input label="RUC" :value="$ruc" disabled description="El RUC no se puede cambiar." />
                @else
                    <flux:input wire:model="ruc" label="RUC" maxlength="11" inputmode="numeric" required autofocus placeholder="20123456789" />
                @endif
                <flux:input wire:model="email" label="Correo de la empresa" type="email" placeholder="facturacion@miempresa.pe" />
            </div>

            <flux:input wire:model="razon_social" label="Razón social" required placeholder="MI EMPRESA S.A.C." />
            <flux:input wire:model="nombre_comercial" label="Nombre comercial" placeholder="Opcional" />

            <flux:separator text="Domicilio fiscal" />

            <flux:input wire:model="direccion" label="Dirección" required placeholder="AV. LOS OLIVOS 123" />

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="departamento" label="Departamento" required placeholder="LIMA" />
                <flux:input wire:model="provincia" label="Provincia" required placeholder="LIMA" />
                <flux:input wire:model="distrito" label="Distrito" required placeholder="MIRAFLORES" />
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="ubigeo" label="Ubigeo" required maxlength="6" inputmode="numeric" placeholder="150122"
                    description="6 dígitos. Figura en su Ficha RUC." />
                <flux:input wire:model="urbanizacion" label="Urbanización" placeholder="Opcional" />
                <flux:input wire:model="codigo_local" label="Código de establecimiento" required maxlength="4" inputmode="numeric"
                    description="0000 = domicilio fiscal." />
            </div>
        </div>

        {{-- 2. Logo --}}
        <div class="space-y-4">
            <flux:separator text="Logo" />

            <div class="flex flex-wrap items-center gap-6">
                <div class="flex size-28 items-center justify-center overflow-hidden rounded-lg border border-dashed border-zinc-300 bg-white p-2 dark:border-zinc-600">
                    @if ($logo && $logo->isPreviewable() && ! $errors->has('logo'))
                        <img src="{{ $logo->temporaryUrl() }}" alt="Vista previa del logo" class="max-h-full max-w-full object-contain">
                    @elseif ($logoActual && ! $quitarLogo)
                        <img src="{{ $logoActual }}" alt="Logo actual" class="max-h-full max-w-full object-contain">
                    @else
                        <flux:icon.photo class="size-8 text-zinc-400" />
                    @endif
                </div>

                <flux:field class="flex-1">
                    <flux:label>Logo para sus comprobantes</flux:label>
                    <input type="file" wire:model="logo" accept="image/png,image/jpeg" data-test="logo"
                           class="block w-full text-sm text-zinc-600 file:me-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-100" />
                    <flux:description>PNG o JPG, máximo 1 MB. Se imprime en la factura y la boleta (A4 y ticket).</flux:description>
                    <flux:error name="logo" />
                    @if ($logoActual && ! $logo)
                        <flux:checkbox wire:model.live="quitarLogo" label="Quitar el logo actual" class="mt-2" />
                    @endif
                </flux:field>
            </div>
        </div>

        {{-- 3. Conexión SUNAT (solo al registrar) --}}
        @unless ($empresaId)
            <div class="space-y-4">
                <flux:separator text="Conexión con SUNAT" />

                <flux:radio.group wire:model.live="entorno" label="¿Cómo va a emitir?" variant="cards" class="grid gap-3 sm:grid-cols-2">
                    <flux:radio value="beta" label="Modo prueba (beta)"
                                description="Los comprobantes van al ambiente de pruebas de SUNAT y no tienen valor tributario. Ideal para conectar y probar sus sistemas." />
                    <flux:radio value="produccion" label="Modo producción"
                                description="Comprobantes reales, con efectos tributarios. Requiere certificado digital y clave SOL." />
                </flux:radio.group>
                <flux:error name="entorno" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label :badge="$entorno === 'produccion' ? 'Obligatorio' : 'Opcional'">Certificado digital</flux:label>
                        <input type="file" wire:model="certificado" accept=".pfx,.p12,.pem" data-test="certificado"
                               class="block w-full text-sm text-zinc-600 file:me-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-100" />
                        <flux:description>.pfx o .p12 (con contraseña) o .pem. En modo prueba puede cargarlo después.</flux:description>
                        <flux:error name="certificado" />
                    </flux:field>
                    <flux:input wire:model="clave_certificado" label="Contraseña del certificado" type="password" viewable autocomplete="off" />
                </div>

                @if ($entorno === 'produccion')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="sol_usuario" label="Usuario SOL secundario" placeholder="FACTURA1" autocomplete="off"
                                    description="Solo el usuario, sin el RUC." />
                        <flux:input wire:model="sol_clave" label="Clave SOL" type="password" viewable autocomplete="new-password" />
                    </div>
                @endif
            </div>
        @endunless

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit" data-test="guardar-empresa" wire:loading.attr="disabled" wire:target="guardar,logo,certificado">
                {{ $empresaId ? 'Guardar cambios' : 'Registrar empresa' }}
            </flux:button>
            <flux:button variant="ghost" :href="$empresaId ? route('empresas.show', $empresaUuid) : route('dashboard')" wire:navigate>
                Cancelar
            </flux:button>
        </div>
    </form>
</section>
