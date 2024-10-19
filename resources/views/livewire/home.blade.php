<div>
    <div class="container py-2">
        <header>

            <div class="pricing-header p-3 pb-md-4 mx-auto text-center">
                <h1 class="display-4 fw-normal">{{ $title }}</h1>
                {{-- <p class="fs-5 text-muted">Quickly build an effective pricing table for your potential customers with
                    this Bootstrap example. It’s built with default Bootstrap components and utilities with little
                    customization.</p> --}}

                <a href="{{ $linkTelegram }}" class="w-100 btn btn-lg btn-primary">Pergi Ke TELEGRAM BOT</a>
            </div>
        </header>
    </div>

    <div class="container">
        <div class="mx-auto text-center pb-3">
            <div class="btn-group" role="group" aria-label="Basic outlined example">
                <button type="button" class="btn btn-outline-primary {{ $selectedCategory == 'all' ? 'active' : '' }}"
                    wire:click="changeSelectedCategory('all')">All</button>
                @foreach ($categories as $category)
                    <button type="button"
                        class="btn btn-outline-primary {{ $selectedCategory == $category->id ? 'active' : '' }}"
                        wire:click="changeSelectedCategory('{{ $category->id }}')">{{ $category->name }}</button>
                @endforeach
            </div>
        </div>


        <div class="row row-cols-1 row-cols-md-3 mb-3 text-center">
            @forelse ($products as $product)
                <div class="col" wire:key={{ $product->id }}>
                    <div class="card mb-4 rounded-3 shadow-sm">
                        <div class="card-header py-3">
                            <h4 class="my-0 fw-normal">{{ $product->name }}</h4>
                            <span class="text-muted">({{ $product->category->name }})</span>
                        </div>
                        <div class="card-body">
                            <h1 class="card-title pricing-card-title">
                                Rp.{{ Number::format($product->price, locale: 'ID') }}
                            </h1>
                            <span class="text-muted">10 users included</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col">
                    <h4 class="my-0 fw-normal">Tidak ada produk</h4>
                </div>
            @endforelse

        </div>
    </div>
</div>
