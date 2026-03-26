<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use App\Models\BlogPost;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Title & Facebook embed')
                    ->description('Give the post a name, then paste the embed link from Facebook. You can leave the box size as default unless the preview looks wrong.')
                    ->icon('heroicon-o-link')
                    ->schema([
                        TextInput::make('title')
                            ->label('Post title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Summer promo at Marcelinos')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if (filled($state) && blank($get('slug'))) {
                                    $set('slug', BlogPost::slugFromTitle($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Web address (slug)')
                            ->required()
                            ->maxLength(255)
                            ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                            ->helperText('This becomes marcelinos.com/blog/your-slug — usually filled from the title; change only if you need a shorter link.'),
                        Textarea::make('embed_src')
                            ->label('Facebook embed URL')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if (! is_string($state)) {
                                    return;
                                }
                                $parsed = BlogPost::parseEmbedFieldInput($state);
                                if (! str_contains(trim($state), '<iframe')) {
                                    return;
                                }
                                $set('embed_src', $parsed['embed_src']);
                                if ($parsed['iframe_width'] !== null) {
                                    $set('iframe_width', $parsed['iframe_width']);
                                }
                                if ($parsed['iframe_height'] !== null) {
                                    $set('iframe_height', $parsed['iframe_height']);
                                }
                            })
                            ->helperText('Paste the long https://www.facebook.com/plugins/post.php?… link from the iframe **src**, or paste the whole `<iframe>…</iframe>` code — we’ll pull the link out for you. From Facebook: Post ··· → Embed → copy code.')
                            ->rules([
                                'regex:/^https:\/\/www\.facebook\.com\/plugins\/post\.php(\?.*)?$/i',
                            ])
                            ->validationMessages([
                                'regex' => 'The embed URL must be a Facebook Post plugin URL (https://www.facebook.com/plugins/post.php?...).',
                            ]),
                        TextInput::make('iframe_width')
                            ->label('Embed width (pixels)')
                            ->numeric()
                            ->required()
                            ->default(500)
                            ->minValue(200)
                            ->maxValue(1200)
                            ->helperText('Default 500 is fine for most posts.'),
                        TextInput::make('iframe_height')
                            ->label('Embed height (pixels)')
                            ->numeric()
                            ->required()
                            ->default(655)
                            ->minValue(200)
                            ->maxValue(2000)
                            ->helperText('If the bottom of the post is cut off, increase this number a bit.'),
                    ]),

                Section::make('Short summary (main text on your site)')
                    ->description('This paragraph appears on the blog list and above the Facebook box. Google reads this text — write it in your own words (do not rely on the iframe for SEO).')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('featured')
                            ->collection('featured')
                            ->label('Featured image (optional)')
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull()
                            ->helperText('Shown on the blog list and at the top of the post. Square or landscape photos work best. Leave empty if you only want the Facebook embed.'),
                        Textarea::make('excerpt')
                            ->label('Short summary')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull()
                            ->placeholder('Example: We’re excited to share our new pool hours and weekend breakfast. Read the full update in the post below.')
                            ->helperText('2–4 sentences is enough. Same language you use for guests (e.g. English or Filipino).'),
                        Textarea::make('meta_description')
                            ->label('Google search snippet (optional)')
                            ->rows(3)
                            ->columnSpanFull()
                            ->maxLength(320)
                            ->helperText('Optional. This is the short blurb people may see in Google results. If you leave it empty, we automatically use your short summary above — that is enough for most posts.')
                            ->hintAction(
                                Action::make('copyExcerptToMeta')
                                    ->label('Use summary text')
                                    ->icon('heroicon-m-clipboard-document')
                                    ->action(function (Set $set, Get $get): void {
                                        $set('meta_description', (string) ($get('excerpt') ?? ''));
                                    })
                            ),
                    ]),

                Section::make('Extra SEO & sharing (optional)')
                    ->description('Skip this whole section unless marketing asked for specific keywords, a custom Facebook preview image, or pinning order. Leaving everything here empty is OK.')
                    ->icon('heroicon-o-magnifying-glass')
                    ->collapsed()
                    ->schema([
                        TextInput::make('meta_keywords')
                            ->label('Search keywords (optional)')
                            ->maxLength(500)
                            ->nullable()
                            ->placeholder('e.g. Hilongos resort, Leyte hotel, pool')
                            ->helperText('A few words or short phrases separated by commas. Many search engines ignore this; your short summary matters more.'),
                        TextInput::make('og_image')
                            ->label('Social / Facebook preview image (optional)')
                            ->url()
                            ->maxLength(2048)
                            ->nullable()
                            ->placeholder('https://…')
                            ->helperText('Only if you want a specific picture when someone shares this page. Must be a full https:// link to an image. Otherwise Facebook/Google pick automatically.'),
                        TextInput::make('sort_order')
                            ->label('List priority (number)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(9999)
                            ->required()
                            ->helperText('Use 0 for almost every post (newest by date appears first). Only type a bigger number (e.g. 100) if this story must appear above others. Bigger = higher on the list.'),
                    ]),

                Section::make('Publishing')
                    ->description('Drafts stay in the admin only until you set a publish date.')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        DateTimePicker::make('published_at')
                            ->label('Publish on')
                            ->nullable()
                            ->helperText('Empty = draft (not shown on the public website or app). Set a date/time when the post should go live.'),
                    ]),
            ]);
    }
}
