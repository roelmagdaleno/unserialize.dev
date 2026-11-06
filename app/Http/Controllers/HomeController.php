<?php

namespace App\Http\Controllers;

use App\Data\FrequentlyAskedQuestions;
use Illuminate\Contracts\View\View;
use Laravel\Head\Facades\Head;
use Laravel\Head\Facades\Schema;

/**
 * Serves the home page converter.
 */
class HomeController extends Controller
{
    /**
     * The home page meta description, shared with the route metadata and the
     * structured data so both describe the converter the same way.
     */
    public const string META_DESCRIPTION = 'Paste PHP serialized data and get readable JSON. If it fails with an error at offset, see the broken byte and how to fix it. Nothing is stored.';

    /**
     * The home page Open Graph and Twitter description, shorter than the meta
     * description because social cards already show the title and image.
     */
    public const string SOCIAL_DESCRIPTION = 'Turn PHP serialized data into readable JSON. Paste, convert, done. Nothing you paste is stored.';

    /**
     * The worked example the converter's field starts with.
     */
    private const string EXAMPLE_VALUE = 'a:10:{s:4:"name";s:6:"Chrome";s:7:"version";s:9:"103.0.0.0";s:8:"platform";s:7:"Windows";s:10:"update_url";s:29:"https://www.google.com/chrome";s:7:"img_src";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:11:"img_src_ssl";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:15:"current_version";s:2:"18";s:7:"upgrade";b:0;s:8:"insecure";b:0;s:6:"mobile";b:0;}';

    /**
     * The GitHub profile that identifies the converter's author.
     */
    private const string AUTHOR_GITHUB_URL = 'https://github.com/roelmagdaleno';

    /**
     * The X profile that identifies the converter's author.
     */
    private const string AUTHOR_X_URL = 'https://x.com/Roel7nxju0';

    /**
     * Render the converter page and publish its structured data alongside the
     * head metadata the home route already declares.
     *
     * - The author is published once under an `@id`; the application and site
     *   reference that node instead of repeating it.
     * - `WebSite` belongs on the home page only, where search engines read the
     *   site name from.
     * - The FAQ structured data comes from the same source as the visible
     *   questions, so the two cannot drift.
     */
    public function __invoke(FrequentlyAskedQuestions $questions): View
    {
        $homeUrl = url('/').'/';
        $author = ['@id' => $homeUrl.'#author'];

        Head::schema(
            Schema::person()
                ->set('@id', $author['@id'])
                ->name('Roel Magdaleno Ramón')
                ->url(self::AUTHOR_GITHUB_URL)
                ->sameAs([self::AUTHOR_GITHUB_URL, self::AUTHOR_X_URL])
        );

        Head::schema(
            Schema::webSite()
                ->name('Unserialize')
                ->url($homeUrl)
                ->publisher($author)
        );

        Head::schema(
            Schema::webApplication()
                ->name('Unserialize')
                ->url($homeUrl)
                ->description(self::META_DESCRIPTION)
                ->applicationCategory('DeveloperApplication')
                ->operatingSystem('Any operating system with a modern web browser')
                ->creator($author)
                ->offers(
                    Schema::offer()
                        ->price(0)
                        ->currency('USD')
                )
        );

        Head::schema(Schema::faq()->questions($questions->forStructuredData()));

        return view('home', [
            'questions' => $questions->all(),
            'serializedData' => self::EXAMPLE_VALUE,
        ]);
    }
}
