<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ([route('home'), route('guides.serialization'), route('guides.wordpress'), route('security'), route('privacy')] as $url)
    <url><loc>{{ $url }}</loc></url>
@endforeach
</urlset>
