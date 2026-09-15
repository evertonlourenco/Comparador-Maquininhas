<x-filament-panels::page>
    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
        {{ $this->plano->marca->nome }} — {{ $this->plano->nome }}
    </p>

    <form wire:submit="salvar" class="mt-4">
        {{ $this->form }}

        <div class="fi-form-actions mt-6">
            <x-filament::button type="submit">
                Salvar tabela
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
