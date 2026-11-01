{{-- La primera vez, la barra lateral del escritorio sale plegada. Alpine guarda luego lo que elija cada uno. --}}
<script>
    try {
        if (localStorage.getItem('_x_filament:sidebar:desktop_open') === null) {
            localStorage.setItem('_x_filament:sidebar:desktop_open', 'false');
        }
    } catch (e) {}
</script>
