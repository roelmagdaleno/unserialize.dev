<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ([route('home'), route('privacy'), route('developers')] as $url)
    <url><loc>{{ $url }}</loc></url>
@endforeach
</urlset>
