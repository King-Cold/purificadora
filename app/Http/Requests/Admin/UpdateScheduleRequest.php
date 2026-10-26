<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'opening_time' => ['required', 'date_format:H:i'],
            'break_start_time' => ['required', 'date_format:H:i'],
            'break_end_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i'],
            'order_cutoff_advance_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $open = $this->input('opening_time');
            $breakStart = $this->input('break_start_time');
            $breakEnd = $this->input('break_end_time');
            $close = $this->input('closing_time');
            $advance = (int) $this->input('order_cutoff_advance_minutes');

            $toMinutes = function (string $timeStr): int {
                [$h, $m] = explode(':', $timeStr);
                return ((int) $h) * 60 + ((int) $m);
            };

            $openMin = $toMinutes($open);
            $breakStartMin = $toMinutes($breakStart);
            $breakEndMin = $toMinutes($breakEnd);
            $closeMin = $toMinutes($close);
            $cutoffMin = $closeMin - $advance;

            // 1. apertura < inicio descanso
            if ($openMin >= $breakStartMin) {
                $v->errors()->add('break_start_time', 'El inicio del descanso debe ser posterior a la hora de apertura.');
            }

            // 2. inicio descanso < fin descanso
            if ($breakStartMin >= $breakEndMin) {
                $v->errors()->add('break_end_time', 'El fin del descanso debe ser posterior al inicio del descanso.');
            }

            // 3. fin descanso < cierre
            if ($breakEndMin >= $closeMin) {
                $v->errors()->add('closing_time', 'La hora de cierre debe ser posterior al fin del descanso.');
            }

            // 4. La hora límite de pedidos debe ser posterior al fin del descanso
            if ($cutoffMin <= $breakEndMin) {
                $cutoffFormatted = Carbon::createFromTime((int) floor($cutoffMin / 60), $cutoffMin % 60)->format('H:i');
                $v->errors()->add(
                    'order_cutoff_advance_minutes',
                    "La hora límite de pedidos calculada ({$cutoffFormatted}) debe ser posterior al fin del descanso ({$breakEnd})."
                );
            }
        });
    }

    /**
     * Nombres de atributos personalizados para mensajes de error legibles.
     */
    public function attributes(): array
    {
        return [
            'opening_time' => 'hora de apertura',
            'break_start_time' => 'inicio del descanso',
            'break_end_time' => 'fin del descanso',
            'closing_time' => 'hora de cierre',
            'order_cutoff_advance_minutes' => 'anticipación para cierre de pedidos',
        ];
    }

    /**
     * Mensajes de validación personalizados.
     */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'date_format' => 'El campo :attribute debe tener un formato de hora válido (HH:MM).',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'min' => 'El campo :attribute no puede ser menor a :min.',
        ];
    }
}
