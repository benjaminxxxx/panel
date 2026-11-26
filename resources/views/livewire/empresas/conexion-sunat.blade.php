<flux:card class="space-y-8">
    <div>
        <flux:heading size="lg">Conexión con SUNAT</flux:heading>
        <flux:text>El certificado y la clave SOL se guardan cifrados. Nunca se muestran de nuevo.</flux:text>
    </div>

    <form wire:submit="subirCertificado" class="space-y-4">
        <flux:heading>Certificado digital</flux:heading>

        @if ($empresa->certificado_vence_en)
            <flux:callout :variant="$empresa->certificado_vence_en->isPast() ? 'danger' : ($empresa->certificado_vence_en->diffInDays() < 30 ? 'warning' : 'success')"
                          icon="shield-check"
                          :heading="'Certificado cargado · vence el '.$empresa->certificado_vence_en->format('d/m/Y')"
                          :text="$empresa->certificado_sujeto" />
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ $empresa->certificado_vence_en ? 'Reemplazar certificado' : 'Archivo del certificado' }}</flux:label>
                <input type="file" wire:model="certificado" accept=".pfx,.p12,.pem" data-test="archivo-certificado"
                       class="block w-full text-sm text-zinc-600 file:me-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-100" />
                <flux:description>.pfx o .p12 (con contraseña) o .pem</flux:description>
                <flux:error name="certificado" />
            </flux:field>

            <flux:input wire:model="clave_certificado" label="Contraseña del certificado" type="password" viewable autocomplete="off" />
        </div>

        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="certificado,subirCertificado">
            Cargar certificado
        </flux:button>
    </form>

    <flux:separator />

    <form wire:submit="guardarSunat" class="space-y-4">
        <flux:heading>Clave SOL y entorno</flux:heading>

        <flux:radio.group wire:model.live="entorno" label="Entorno de emisión" variant="segmented">
            <flux:radio value="beta" label="Pruebas (beta)" />
            <flux:radio value="produccion" label="Producción" />
        </flux:radio.group>
        <flux:error name="entorno" />

        @if ($entorno === 'produccion')
            <flux:callout variant="warning" icon="exclamation-triangle"
                          text="En producción cada comprobante emitido es real y tiene efectos tributarios." />
        @else
            <flux:text size="sm">En beta SUNAT acepta cualquier certificado y no pide clave SOL: ideal para probar sus sistemas.</flux:text>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="sol_usuario" label="Usuario SOL secundario" placeholder="FACTURA1" autocomplete="off"
                        description="Solo el usuario, sin el RUC." />
            <flux:input wire:model="sol_clave" label="Clave SOL" type="password" viewable autocomplete="new-password"
                        :placeholder="$empresa->sol_clave ? '•••••••• (guardada)' : ''"
                        description="Déjela vacía para conservar la actual." />
        </div>

        <flux:button type="submit" variant="primary">Guardar</flux:button>
    </form>
</flux:card>
