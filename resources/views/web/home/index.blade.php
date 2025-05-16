@extends('layouts.web')

@section('title', config('setting')['software_name'].' - Casino Online | Welcome to Website!')
@section('seo')
<link rel="canonical" href="{{ url()->current() }}" />
<meta name="description"
    content="{{ trans('home_index.welcome_to') }} {{ config('setting')['software_name'] }} - {{ trans('home_index.the_best_online') }}">
<meta name="keywords" content="{{ config('setting')['software_name'] }}, {{ trans('home_index.online') }}">

<meta property="og:locale" content="pt_BR" />
<meta property="og:type" content="website" />
<meta property="og:title"
    content="{{ config('setting')['software_name'] }} - {{ trans('home_index.Betting') }} | {{ trans('home_index.Slot') }} " />
<meta property="og:description"
    content="{{ trans('home_index.welcome_to') }} {{ config('setting')['software_name'] }} - {{ trans('home_index.the_best_online') }}" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:site_name"
    content="{{ config('setting')['software_name'] }} - {{ trans('home_index.Betting') }} | {{ trans('home_index.Slot') }}" />
<meta property="og:image" content="{{ asset('/assets/images/banner-1.png') }}" />
<meta property="og:image:secure_url" content="{{ asset('/assets/images/banner-1.png') }}" />
<meta property="og:image:width" content="1024" />
<meta property="og:image:height" content="571" />

<meta name="twitter:title"
    content="{{ config('setting')['software_name'] }} - {{ trans('home_index.Betting') }} | {{ trans('home_index.Slot') }}">
<meta name="twitter:description"
    content="{{ trans('home_index.welcome_to') }}  {{ config('setting')['software_name'] }} - {{ trans('home_index.the_best_online') }}">
<meta name="twitter:image" content="{{ asset('/assets/images/banner-1.png') }}">
<!-- {{ trans('home_index.Replace') }} -->
<meta name="twitter:url" content="{{ url('/') }}"> <!-- {{ trans('home_index.Replace_link') }}  -->
<!-- Splide CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@latest/dist/css/splide.min.css">

<!-- Splide JS -->
<script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@latest/dist/js/splide.min.js"></script>

@endsection

@push('styles')
<!--<link rel="stylesheet" href="{{ asset('assets/css/splide-core.min.css') }}">-->
<!--<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">-->
<link href="{{asset('assets/css/style_index.css')}}" rel="stylesheet"  type="text/css">
@endpush

@section('content')
<div class="container-fluid">

    <div class="">
        @include('includes.navbar_top')
        @include('includes.navbar_left')
<div class="page__content2">
       <div class="md:w-4/6 2xl:w-4/6 mx-auto t-4">
                <section id="image-carousel" class="splide" aria-label="">
                    <div class="splide__track">
                        <!-- <div class="splide-banner">
                        {{ trans('home_index.Get_10_free_spins') }} <span style="margin-left: 10px"><i class="fa-solid fa-fire"></i></span>
                    </div> -->
                        <ul class="splide__list">
                            @foreach(\App\Models\Banner::where('type', 'carousel')->get() as $banner)
                            <li class="splide__slide">
                                <a href="{{ $banner->link }}" target="_blank">
                                    <img src="{{ asset('storage/'.$banner->image) }}" alt="">
                                </a>
                            </li>
                            @endforeach
                        </ul>


                    </div>
                    <div class="splide__arrows">
                        <button class="splide__arrow splide__arrow--prev">
                            &lt;
                            <!-- Icon or text for previous -->
                        </button>
                        <button class="splide__arrow splide__arrow--next">
                            &gt;
                            <!-- Icon or text for next -->
                        </button>
                    </div>
                </section>
                <section class="carousel" dir="ltr" aria-label="Gallery" tabindex="0">
                    <div class="carousel__viewport">
                        <ol class="carousel__track"
                            style="transform: translateX(0px); width: 100%; transition: all 0ms ease 0s;">
                            @foreach(\App\Models\Banner::where('type', 'carouselmini')->get() as $banner)
                            <li class="carousel__slide carousel__slide--visible carousel__slide--prev"
                                aria-hidden="false" style="width: 33.3333%;">
                                <div class="carousel__item min-h-[60px] md:min-h-[150px] rounded w-full mr-4">                                  
                                    <div class="w-full h-full rounded"> <a href="{{ $banner->link }}" target="_blank"><img src="{{ asset('storage/'.$banner->image) }}" alt=""
                                            class="h-full w-full rounded"></a></div>                                      
                                </div>
                            </li>
                            @endforeach                       
                    </div>
                </section>
            </div>
</div>
        <div class="page__content" style="">
        <div class="md:w-4/6 2xl:w-4/6 mx-auto ">     
           <script>
            document.addEventListener('DOMContentLoaded', function() {
                new Splide('#image-carousel', {
                    type: 'loop',
                    perPage: 1,
                    perMove: 1,
                    arrows: true, // Enable the next and previous arrows
                    pagination: false, // Optional: Disable pagination if not needed
                }).mount();
            });
            </script>
            <!-- Search -->
          <form id="searchForm" action="{{ url('/') }}" method="GET">
    <div class="input-group input-search-group">
        <input type="text" name="search" value="{{ request('search') }}" class="form-control"
               placeholder="{{ trans('home_index.Search') }}" aria-label="{{ trans('home_index.To_look') }}"
               aria-describedby="button-search">
        <button class="input-group-text" id="button-search" type="submit">
            <i class="fa-duotone fa-magnifying-glass"></i>
        </button>
    </div>
</form>
        
            <!-- {{ trans('home_index.Platform') }} -->
            @if(count($gamesExclusives) > 0)
            <div class="mt-5">
                @include('includes.title', ['link' => url('/games?tab=exclusives'), 'title' => 'Home Games', 'icon' =>
                'fa-regular fa-gamepad-modern'])
            </div>

            <div class="row mt-2">
                @foreach(\App\Models\Banner::where('type', 'home')->get() as $banner)
                <div class="col-lg-4 mb-3">
                    <a href="{{ $banner->link }}"><img src="{{ asset('storage/'.$banner->image) }}" alt=""
                            class="img-fluid rounded-4 w-full"></a>
                </div>
                @endforeach
            </div>
            <div class="d-steam-cards js-steamCards">
                @foreach($gamesExclusives as $gamee)
                <a href="{{ route('web.vgames.show', ['game' => $gamee->uuid]) }}" class="d-steam-card-wrapper">
                    <div class="d-steam-card js-steamCard" style="background-image: url('storage/{{ $gamee->cover }}')">
                    </div>
                </a>
                @endforeach
            </div>
            @endif
            <br>
           
            @if(count($providers) > 0)
            @foreach($providers as $provider)
            @include('includes.title', ['link' => url('/games?provider='.$provider->code.'&tab=fivers'), 'title' =>
            $provider->name, 'icon' => 'fa-duotone fa-gamepad-modern'])
            <div class="row row-cols-3 row-cols-md-6 mt-3">
                @foreach($provider->games->where('status', 1) as $gameProvider)
                <div class="col mb-3">
                    <a href="{{ route('web.fivers.show', ['code' => $gameProvider->game_code]) }}" class="">
                        <img src="{{ asset('storage/'.$gameProvider->banner) }}" alt="{{ $gameProvider->game_name }}"
                            class="w-full rounded-3">
                    </a>
                </div>
                @endforeach
            </div>
            @endforeach
            @endif
            <!-- Slotegrator -->
            @if(count($games) > 0)
            @include('includes.title', ['link' => url('/games?tab=all'), 'title' => 'House Games', 'icon' => 'fa-duotone
            fa-gamepad-modern'])

            <div class="row mt-3">
                @foreach($games as $game)

                <a href="{{ route('web.fivers.show', ['code' => $game->uuid]) }}" target="_blank" class="d-steam-card-wrapper">
                    <img src="storage/{{ $game->image }}" alt="{{ $game->title }}" class="img-fluid rounded-3">
                </a>

                @endforeach
            </div>
            @endif
            
               @if(count($games) > 0)
            @foreach($categories as $category)
         
            @include('includes.title', ['link' => url('/games?tab=' . urlencode($category->name)),'title' => $category->name_content,'icon' => 'fa-duotone fa-gamepad-modern'])
            <div class="row mt-3">
            @foreach($games->where('category_id', $category->id) as $game)
            <a href="{{ route('web.fivers.show', ['code' => $game->uuid]) }}" target="_blank" class="d-steam-card-wrapper">
                <img src="storage/{{ $game->image }}" alt="{{ $game->title }}" class="img-fluid rounded-3">
            </a>
            @endforeach
            </div>
         @endforeach
         @endif
            
            
            @if(count($games) > 0)
            @include('includes.title', ['link' => url('/games?tab=all'), 'title' => 'Other Games', 'icon' =>
            'fa-duotone fa-gamepad-modern'])

           <div class="row mt-3">
                @foreach($slotgen as $game)
                    @if($game->title == "Lucky Tank") 
                    <a href="{{route('web.'.$game->name.'.site.launch')}}" target="_blank" class="d-steam-card-wrapper">                        
                        @else 

                        <a href="{{route('web.'.$game->name.'.site.launch')}}" target="_blank" class="d-steam-card-wrapper">
                            
                            @endif
                            <img src="{{ $game->logo }}" alt="{{ $game->title }}" class="img-fluid rounded-3">
                        </a>
                @endforeach
            </div>
            @endif
            
                
            <div class="mt-5">
                @include('includes.title', ['link' => url('como-funciona'), 'title' => 'F.A.Q', 'icon' => 'fa-light
                fa-circle-info', 'labelLink' => 'know more'])
            </div>
            @include('web.home.sections.faq')
            @include('includes.footer')
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('searchForm').addEventListener('submit', function(event) {
        var searchInput = document.querySelector('input[name="search"]');
        if (!searchInput.value.trim()) {
            event.preventDefault();  
        }
    });
</script>

<script src="{{ asset('assets/js/splide.min.js') }}"></script>
 
<script>
document.addEventListener('DOMContentLoaded', function() {
        var isLoggedIn = {{ auth()->check() ? 'true' : 'false' }};
        
        if (!isLoggedIn) {
            var links = document.querySelectorAll('a[target="_blank"]');
            links.forEach(function(link) {
                link.removeAttribute('target');
            });
        }
    });
    
document.addEventListener('DOMContentLoaded', function() {
    var elemento = document.getElementById('splide-soccer');

    if (elemento) {
        new Splide('#splide-soccer', {
            type: 'loop',
            drag: 'free',
            focus: 'center',
            autoplay: 'play',
            perPage: 3,
            arrows: false,
            pagination: false,
            breakpoints: {
                640: {
                    perPage: 1,
                },
            },
            autoScroll: {
                speed: 1,
            },
        }).mount();
    }

    new Splide('#image-carousel', {
        arrows: false,
        pagination: false,
        type: 'loop',
        autoplay: 'play',
    }).mount();
});
</script>



@endpush