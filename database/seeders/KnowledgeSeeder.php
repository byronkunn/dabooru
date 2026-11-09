<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\WikiPage;
use Illuminate\Database\Seeder;

class KnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['digital painting', 'entity', 'Artwork painted using digital tools. Do not use this tag simply because a traditional work was scanned.'],
            ['watercolor', 'entity', 'Artwork made using watercolor pigment. Describe the actual medium rather than the file format.'],
            ['portrait', 'entity', 'Artwork focused on a person or character. Creator attribution belongs in the credit fields, not this tag.'],
            ['hands', 'anatomies', 'Use when hands are a significant visual subject. Anatomy tags describe the work, not a contributor.'],
            ['lingerie', 'anatomies', 'Use when lingerie is visibly depicted. Clothing tags do not replace the work content rating.'],
            ['artistic nudity', 'genre', 'Use for artwork depicting nudity in an artistic context. Assign the appropriate content rating separately; this tag does not imply that a work is safe for all viewers.'],
            ['romance', 'genre', 'Use when a romantic relationship is a significant depicted theme. Do not infer the personal relationships or orientation of creators.'],
            ['nudity', 'genre', 'Warn viewers when nudity is depicted. Content warnings complement, rather than replace, the required work rating.'],
            ['sexual content', 'genre', 'A general warning for sexual subject matter. Rate the work appropriately and follow the content policy. Do not use tags to legitimize prohibited content.'],
            ['flashing imagery', 'genre', 'Warn viewers about flashing or strobing imagery. This warning is independent of the adult-content rating.'],
            ['original character', 'characters', 'A character created independently rather than belonging to an established series. Credit its creator using supported attribution fields.'],
            ['pixel art', 'genre', 'Artwork intentionally constructed with visible pixel-level shapes. Low resolution alone is not sufficient.'],
        ];
        foreach ($tags as [$name, $category, $wiki]) {
            Tag::firstOrCreate(['name' => $name], ['category' => $category, 'wiki' => $wiki]);
        }
        WikiPage::firstOrCreate(['title' => 'Community tagging and attribution'], [
            'body' => "## Describe works, not people\n\nChoose tags for what is depicted, its medium, and useful discovery information. Do not infer a contributor's identity, anatomy, relationships, or preferences.\n\n## Credit is separate\n\nArtist tags and community wiki pages do not establish ownership, membership, consent, endorsement, or reposting permission. Use the artist credit and source fields. Only moderators can link an artist page to a verified member after checking identity.\n\n## Contribute transparently\n\nExplain your change and cite public sources for factual claims. New contributors submit for review. Five independently moderator-approved edits unlock ordinary publishing. Renames, aliases, relationships, merges, and bulk changes still need moderator review.\n\n## Correct mistakes\n\nUse history to compare before and after. Discuss disputed definitions, report vandalism, and ask moderators to revert incorrect edits. Never publish private personal information.",
        ]);
        WikiPage::firstOrCreate(['title' => 'Content ratings and warnings'], [
            'body' => "## Ratings are mandatory\n\nWorks and wiki articles have separate SFW, Suggestive, or Explicit ratings. Tags never override content settings or age gates. Wiki example galleries and counts respect visibility and blocking preferences.\n\n## Adult-site categories\n\nUse genre, characters, franchise, anatomies, poses, activity, substance, settings, language, entity, and artist categories. Tags describe depictions, not contributors.\n\n## Warnings help viewers\n\nUse neutral warnings for nudity, sexual subject matter, flashing imagery, or other relevant viewing concerns. Warnings supplement the rating; they do not establish consent or prove anyone's age.\n\n## Policy still applies\n\nTagging never makes prohibited material acceptable. Follow the site's content policy and report suspected violations. Do not embed external images in wiki articles.",
        ]);
        WikiPage::firstOrCreate(['title' => 'Tag categories for artwork'], [
            'body' => "## Genre and characters\n\nDescribe the work genre and adult characters depicted. Do not infer personal identity or orientation from artwork.\n\n## Franchise and entity\n\nIdentify the originating franchise and type of depicted entity.\n\n## Anatomies, poses, and activity\n\nDescribe visible anatomy, poses, and activities. Use only tags supported by the work.\n\n## Substance and settings\n\nRecord depicted substances and locations when relevant.\n\n## Language and artist\n\nUse language tags for the work language. Use supported attribution fields to credit artists.\n\n## Adult-content discovery\n\nUse appropriate work ratings independently. Tags never establish age, consent, personal identity, or permission to share. Follow the content policy.\n\n## Tagging principles\n\nUse only supported tags from the 11 current categories. Tags do not establish age, consent, personal identity, or permission to share.",
        ]);
    }
}
