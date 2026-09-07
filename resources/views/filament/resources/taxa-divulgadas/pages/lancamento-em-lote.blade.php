<x-filament-panels::page>
    <form wire:submit="lancar">
        {{ $this->form }}

        <div class="fi-form-actions mt-6">
            <x-filament::button type="submit">
                Lançar tabela
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
