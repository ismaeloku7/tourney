<?php

use Illuminate\Support\Facades\Schedule;

// Los resultados de jugadores sin validar a tiempo cuentan solos.
Schedule::command('resultados:validar-vencidos')->everyMinute()->withoutOverlapping();
