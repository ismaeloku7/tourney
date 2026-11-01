{{-- El color lo elige el organizador: el CSS compilado solo lee esta variable. --}}
<style>
    :root {
        --panel-acento: {{ \App\Filament\ColorDelPanel::hex() }};
    }
</style>
