{{-- Lo último que ha hecho el jugador, o el error de lo que ha intentado. --}}
@if ($aviso)
    <p class="rounded-[3px] bg-green-50 p-3 text-sm text-green-900">{{ $aviso }}</p>
@endif
@error('portal') <p class="rounded-[3px] bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p> @enderror
