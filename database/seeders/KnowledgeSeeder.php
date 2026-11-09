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
            ['digital painting', 'medium', 'Artwork painted using digital tools. Do not use this tag simply because a traditional work was scanned.'],
            ['watercolor', 'medium', 'Artwork made using watercolor pigment. Describe the actual medium rather than the file format.'],
            ['portrait', 'subject', 'Artwork focused on a person or character. Creator attribution belongs in the credit fields, not this tag.'],
            ['hands', 'anatomy', 'Use when hands are a significant visual subject. Anatomy tags describe the work, not a contributor.'],
            ['lingerie', 'clothing', 'Use when lingerie is visibly depicted. Clothing tags do not replace the work content rating.'],
            ['artistic nudity', 'themes', 'Use for artwork depicting nudity in an artistic context. Assign the appropriate content rating separately; this tag does not imply that a work is safe for all viewers.'],
            ['romance', 'relationships', 'Use when a romantic relationship is a significant depicted theme. Do not infer the personal relationships or orientation of creators.'],
            ['nudity', 'content warnings', 'Warn viewers when nudity is depicted. Content warnings complement, rather than replace, the required work rating.'],
            ['sexual content', 'content warnings', 'A general warning for sexual subject matter. Rate the work appropriately and follow the content policy. Do not use tags to legitimize prohibited content.'],
            ['flashing imagery', 'content warnings', 'Warn viewers about flashing or strobing imagery. This warning is independent of the adult-content rating.'],
            ['original character', 'character', 'A character created independently rather than belonging to an established series. Credit its creator using supported attribution fields.'],
            ['pixel art', 'style', 'Artwork intentionally constructed with visible pixel-level shapes. Low resolution alone is not sufficient.'],
        ];
        foreach ($tags as [$name, $category, $wiki]) {
            Tag::firstOrCreate(['name' => $name], ['category' => $category, 'wiki' => $wiki]);
        }
        WikiPage::firstOrCreate(['title' => 'Community tagging and attribution'], [
            'body' => "## Describe works, not people\n\nChoose tags for what is depicted, its medium, and useful discovery information. Do not infer a contributor's identity, anatomy, relationships, or preferences.\n\n## Credit is separate\n\nArtist tags and community wiki pages do not establish ownership, membership, consent, endorsement, or reposting permission. Use the artist credit and source fields. Only moderators can link an artist page to a verified member after checking identity.\n\n## Contribute transparently\n\nExplain your change and cite public sources for factual claims. New contributors submit for review. Five independently moderator-approved edits unlock ordinary publishing. Renames, aliases, relationships, merges, and bulk changes still need moderator review.\n\n## Correct mistakes\n\nUse history to compare before and after. Discuss disputed definitions, report vandalism, and ask moderators to revert incorrect edits. Never publish private personal information.",
        ]);
        WikiPage::firstOrCreate(['title' => 'Content ratings and warnings'], [
            'body' => "## Ratings are mandatory\n\nWorks and wiki articles have separate SFW, Suggestive, or Explicit ratings. Tags never override content settings or age gates. Wiki example galleries and counts respect visibility and blocking preferences.\n\n## Adult-site categories\n\nUse artist, character, series, medium, style, subject, anatomy, clothing, position, appearance, setting, composition, relationships, themes, content warnings, and general categories. Anatomy and relationship tags describe depictions, not contributors.\n\n## Warnings help viewers\n\nUse neutral warnings for nudity, sexual subject matter, flashing imagery, or other relevant viewing concerns. Warnings supplement the rating; they do not establish consent or prove anyone's age.\n\n## Policy still applies\n\nTagging never makes prohibited material acceptable. Follow the site's content policy and report suspected violations. Do not embed external images in wiki articles.",
        ]);
        WikiPage::firstOrCreate(['title' => 'Tag categories for artwork'], [
            'body' => "## Clothing / wearing\n\nDescribe visible garments and accessories, including lingerie, swimwear, masks, and headwear.\n\n## Pose / position\n\nDescribe the depicted pose, such as standing, seated, kneeling, lying down, or folded arms. Do not infer activities not shown.\n\n## Appearance and anatomy\n\nAppearance covers visible hair, eyes, and character features. Anatomy describes body details significant to the artwork, not facts about an uploader.\n\n## Setting and composition\n\nSetting describes locations and backgrounds. Composition describes framing, lighting, viewpoint, and visual layout.\n\n## Adult-content discovery\n\nUse neutral content warnings and appropriate work ratings independently. Tags never establish age, consent, personal identity, or permission to share. Follow the content policy.\n\n## Other categories\n\nArtist, character, series, medium, style, subject, relationships, and themes remain available. General holds tags that need further review.",
        ]);
    }
}
