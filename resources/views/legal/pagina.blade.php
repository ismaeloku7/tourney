<x-layouts.publico :titulo="$titulo">
    <div class="mx-auto max-w-3xl px-4 py-12">
        <h1 class="text-2xl font-semibold">{{ $titulo }}</h1>
        @if ($organizador)
            @if ($texto)
                <div class="prose mt-6 max-w-none whitespace-pre-line text-neutral-800">{{ $texto }}</div>
            @else
                <p class="mt-6 text-neutral-600">{{ $organizador->nombre }} todavía no ha publicado este texto. Puede pedírselo en su dirección de contacto.</p>
            @endif
        @else
            @php($faltan = array_filter($titular, fn ($v) => $v === null || $v === ''))
            @if ($faltan !== [])
                <p class="mt-6 text-neutral-600">La plataforma todavía no ha publicado los datos de su titular. Escriba a quien la gestiona para pedirlos.</p>
            @else
                <dl class="mt-6 grid gap-2 text-neutral-800 sm:grid-cols-[12rem_1fr]">
                    <dt class="text-neutral-500">Titular</dt><dd>{{ $titular['nombre'] }}</dd>
                    <dt class="text-neutral-500">NIF</dt><dd>{{ $titular['nif'] }}</dd>
                    <dt class="text-neutral-500">Domicilio</dt><dd>{{ $titular['domicilio'] }}</dd>
                    <dt class="text-neutral-500">Contacto</dt><dd>{{ $titular['email'] }}</dd>
                </dl>
                @if ($esPrivacidad)
                    <div class="mt-8 space-y-4 text-neutral-800">
                        <p>Los datos que se recogen al crear una organización (nombre, correo y contraseña del responsable) se usan solo para dar de alta la organización y comunicarnos con ella sobre el servicio. La base legal es la ejecución del servicio solicitado.</p>
                        <p>Cada organización es responsable de los datos de sus jugadores e inscritos; la plataforma los trata por encargo suyo y no los usa para otros fines. Los datos se guardan mientras la organización esté dada de alta y se borran al darse de baja, tras exportarlos.</p>
                        <p>Puede ejercer sus derechos de acceso, rectificación, supresión, oposición, limitación y portabilidad escribiendo al contacto de arriba.</p>
                    </div>
                @else
                    <p class="mt-8 text-neutral-800">Este sitio web presta un servicio de gestión de torneos a las organizaciones dadas de alta. Cada organización es responsable de los contenidos que publica en su dirección.</p>
                @endif
            @endif
        @endif
    </div>
</x-layouts.publico>
