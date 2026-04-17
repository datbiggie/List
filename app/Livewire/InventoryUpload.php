<?php
namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Contracts\InventoryParserInterface;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Storage;

class InventoryUpload extends Component
{
    use WithFileUploads;

    // Restauramos la validación, pero SIN el límite de "max:10240"
    #[Validate('required|file|mimes:xlsx,xls')]
    public $localFile;

    #[Validate('required|file|mimes:xlsx,xls')]
    public $supplierFile;

    public bool $isProcessing = false;

    public function processFiles(InventoryParserInterface $parser)
    {
        // Ahora esto funcionará perfectamente porque las reglas existen nuevamente
        $this->validate();
        
        $this->isProcessing = true;

        try {
            $localPath = $this->localFile->store('imports', 'local');
            $supplierPath = $this->supplierFile->store('imports', 'local');

            $absoluteLocalPath = Storage::disk('local')->path($localPath);
            $absoluteSupplierPath = Storage::disk('local')->path($supplierPath);

            $parser->parseLocal($absoluteLocalPath);
            $parser->parseSupplier($absoluteSupplierPath);

            $this->reset(['localFile', 'supplierFile']);
            
            return redirect()->route('reconciliation.board');

        } catch (\Exception $e) {
            $this->addError('upload', 'Error al procesar los archivos: ' . $e->getMessage());
            $this->isProcessing = false;
        }
    }

    public function render()
    {
        return view('livewire.inventory-upload');
    }
}