<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Product;
use App\Settings\TelegramSettings;
use Livewire\Attributes\Title;
use Livewire\Component;

class Home extends Component
{
    protected $telegramSettings;

    public $title = 'Home';
    public $linkTelegram;

    public $products;
    public $categories;

    public $selectedCategory = 'all';

    public function __construct()
    {
        $this->telegramSettings = new TelegramSettings();
        $this->title = $this->telegramSettings->store_name;
        $this->linkTelegram = $this->telegramSettings->bot_url;
    }

    public function mount()
    {
        $this->categories = Category::query()
            ->orderBy('name', 'asc')
            ->get();

        $this->products = Product::query()
            ->select('id', 'name', 'price', 'category_id', 'description')
            ->active()
            ->orderBy('created_at', 'desc')
            ->with('category')
            ->get();
    }

    public function changeSelectedCategory($value)
    {
        $this->selectedCategory = $value;
        $this->products = Product::query()
            ->select('id', 'name', 'price', 'category_id', 'description')
            ->active()
            ->orderBy('created_at', 'desc')
            ->when($this->selectedCategory != 'all', function ($query) {
                return $query->where('category_id', $this->selectedCategory);
            })
            ->with('category')
            ->get();
    }


    public function render()
    {
        return view('livewire.home')
            ->title($this->title);
    }
}
