<?php
/** The fixed Klyval taxonomy. Owned by code. The admin can ASSIGN these, never edit or extend them. */
final class Taxonomy {
    public const PILLARS = [
        'build'  => ['label' => 'Build',  'lead' => 'We build websites, applications and digital systems that solve real problems.'],
        'create' => ['label' => 'Create', 'lead' => 'We create digital content and visual experiences that communicate clearly.'],
        'launch' => ['label' => 'Launch', 'lead' => 'We help bring products, brands and digital experiences to market.'],
    ];
    public const KINDS = ['client' => 'Client Work', 'product' => 'Klyval Product / Venture', 'experiment' => 'Internal Experiment'];
    public const PROJECT_STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];
    public const PLATFORMS = ['x' => 'X', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'github' => 'GitHub', 'behance' => 'Behance', 'dribbble' => 'Dribbble', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'website' => 'Website'];
    public const ENQUIRY_STATUSES = ['new' => 'New', 'reviewing' => 'Reviewing', 'contacted' => 'Contacted', 'in_progress' => 'In Progress', 'closed' => 'Closed'];
    public const ARTICLE_CATEGORIES = ['studio' => 'Studio', 'build' => 'Build', 'create' => 'Create', 'launch' => 'Launch'];
    public const PROJECT_TYPES = ['Website', 'Digital Product', 'Web Application', 'Business System', 'Content / Video', 'Campaign', 'Launch', 'Other'];
    /** Presentation only: groups the existing PROJECT_TYPES under the three pillars. Submitted values are still validated against PROJECT_TYPES. */
    public const PROJECT_TYPE_GROUPS = ['Build' => ['Website', 'Digital Product', 'Web Application', 'Business System'], 'Create' => ['Content / Video'], 'Launch' => ['Campaign', 'Launch'], 'Something else' => ['Other']];
    /** Studio-level links exclude the personal "website" platform. */
    public static function studioPlatforms(): array { return array_diff_key(self::PLATFORMS, ['website' => 1]); }
}
