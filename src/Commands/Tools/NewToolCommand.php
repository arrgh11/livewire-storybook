<?php

namespace Arrgh11\WireBook\Commands\Tools;

use Arrgh11\WireBook\Commands\Traits;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

class NewToolCommand extends Command
{

    use Traits\CanManipulateFiles;

    protected $signature = 'wirebook:tool {--force}';

    protected $description = 'Create a new Tool';

    public function handle(): int
    {

        //ask for the tool name
        $name = text(label: 'What is the Tool\'s name?', required: true);

        //create a group for the story if it doesn't exist
        $studlyGroup = Str::studly($group);

        //make a new Story component with the name in the App\WireBook\Stories\{group} namespace
        $studlyName = Str::studly($name);

        //make a new Story view with the name in the resources/views/wirebook/stories/{group} directory

        $storyView = str($studlyName)
            ->prepend(
                (string) str("App\\WireBook\\Stories\\{$studlyGroup}\\")
                    ->replaceFirst('App\\', '')
            )
            ->replace('\\', '/')
            ->explode('/')
            ->map(fn ($segment) => Str::lower($segment))
            ->implode('.');

        $storyPath = (string) str($studlyName)
            ->prepend('/')
            ->prepend(app_path('Wirebook/Stories/')."\\{$studlyGroup}\\")
            ->replace('\\', '/')
            ->replace('//', '/')
            ->append('.php');

        $viewPath = resource_path(
            (string) str($storyView)
                ->replace('.', '/')
                ->prepend('views/')
                ->append('.blade.php'),
        );

        $files = [
            $storyPath,
            $viewPath
        ];

        if (! $this->option('force') && $this->checkForCollision($files)) {
            $this->error('Collision detected. Aborting.');
            return 0;
        }

        $this->copyStubToApp('Story', $storyPath, [
            'namespace' => "App\\WireBook\\Stories\\{$studlyGroup}",
            'story' => $studlyName,
            'viewpath' => $storyView,
        ]);

        $this->copyStubToApp('StoryView', $viewPath);

        $this->info('Story created successfully.');

        return 1;

    }

    public function old_handle(): int
    {
        $page = (string) str(
            $this->argument('name') ??
            text(
                label: 'What is the page name?',
                placeholder: 'EditSettings',
                required: true,
            ),
        )
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');
        $pageClass = (string) str($page)->afterLast('\\');
        $pageNamespace = str($page)->contains('\\') ?
            (string) str($page)->beforeLast('\\') :
            '';

        $resource = null;
        $resourceClass = null;
        $resourcePage = null;

        $panel = $this->option('panel');

        if ($panel) {
            $panel = Filament::getPanel($panel, isStrict: false);
        }

        if (! $panel) {
            $panels = Filament::getPanels();

            /** @var Panel $panel */
            $panel = (count($panels) > 1) ? $panels[select(
                label: 'Which panel would you like to create this in?',
                options: array_map(
                    fn (Panel $panel): string => $panel->getId(),
                    $panels,
                ),
                default: Filament::getDefaultPanel()->getId()
            )] : Arr::first($panels);
        }

        $resourceInput = $this->option('resource') ?? suggest(
            label: 'Which resource would you like to create this in?',
            options: collect($panel->getResources())
                ->filter(fn (string $namespace): bool => str($namespace)->contains('\\Resources\\'))
                ->map(
                    fn (string $namespace): string => (string) str($namespace)
                        ->afterLast('\\Resources\\')
                        ->beforeLast('Resource')
                )
                ->all(),
            placeholder: '[Optional] UserResource',
        );

        if (filled($resourceInput)) {
            $resource = (string) str($resourceInput)
                ->studly()
                ->trim('/')
                ->trim('\\')
                ->trim(' ')
                ->replace('/', '\\');

            if (! str($resource)->endsWith('Resource')) {
                $resource .= 'Resource';
            }

            $resourceClass = (string) str($resource)
                ->afterLast('\\');

            $resourcePage = $this->option('type') ?? select(
                label: 'Which type of page would you like to create?',
                options: [
                    'custom' => 'Custom',
                    'ListRecords' => 'List',
                    'CreateRecord' => 'Create',
                    'EditRecord' => 'Edit',
                    'ViewRecord' => 'View',
                    'ManageRelatedRecords' => 'Relationship',
                    'ManageRecords' => 'Manage',
                ],
                default: 'custom'
            );

            if ($resourcePage === 'ManageRelatedRecords') {
                $relationship = (string) str(text(
                    label: 'What is the relationship?',
                    placeholder: 'members',
                    required: true,
                ))
                    ->trim(' ');

                $recordTitleAttribute = (string) str(text(
                    label: 'What is the title attribute?',
                    placeholder: 'name',
                    required: true,
                ))
                    ->trim(' ');

                $tableHeaderActions = [];

                $tableHeaderActions[] = 'Tables\Actions\CreateAction::make(),';

                if ($hasAssociateAction = confirm('Is this a one-to-many relationship where the related records can be associated?')) {
                    $tableHeaderActions[] = 'Tables\Actions\AssociateAction::make(),';
                } elseif ($hasAttachAction = confirm('Is this a many-to-many relationship where the related records can be attached?')) {
                    $tableHeaderActions[] = 'Tables\Actions\AttachAction::make(),';
                }

                $tableHeaderActions = implode(PHP_EOL, $tableHeaderActions);

                $tableActions = [];

                if (confirm('Would you like an action to open each record in a read-only View modal?')) {
                    $tableActions[] = 'Tables\Actions\ViewAction::make(),';
                }

                $tableActions[] = 'Tables\Actions\EditAction::make(),';

                if ($hasAssociateAction) {
                    $tableActions[] = 'Tables\Actions\DissociateAction::make(),';
                }

                if ($hasAttachAction ?? false) {
                    $tableActions[] = 'Tables\Actions\DetachAction::make(),';
                }

                $tableActions[] = 'Tables\Actions\DeleteAction::make(),';

                if ($hasSoftDeletes = confirm('Can the related records be soft deleted?')) {
                    $tableActions[] = 'Tables\Actions\ForceDeleteAction::make(),';
                    $tableActions[] = 'Tables\Actions\RestoreAction::make(),';
                }

                $tableActions = implode(PHP_EOL, $tableActions);

                $tableBulkActions = [];

                if ($hasAssociateAction) {
                    $tableBulkActions[] = 'Tables\Actions\DissociateBulkAction::make(),';
                }

                if ($hasAttachAction ?? false) {
                    $tableBulkActions[] = 'Tables\Actions\DetachBulkAction::make(),';
                }

                $tableBulkActions[] = 'Tables\Actions\DeleteBulkAction::make(),';

                $modifyQueryUsing = '';

                if ($hasSoftDeletes) {
                    $modifyQueryUsing .= '->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([';
                    $modifyQueryUsing .= PHP_EOL . '    SoftDeletingScope::class,';
                    $modifyQueryUsing .= PHP_EOL . ']))';

                    $tableBulkActions[] = 'Tables\Actions\RestoreBulkAction::make(),';
                    $tableBulkActions[] = 'Tables\Actions\ForceDeleteBulkAction::make(),';
                }

                $tableBulkActions = implode(PHP_EOL, $tableBulkActions);
            }
        }

        if (empty($resource)) {
            $pageDirectories = $panel->getPageDirectories();
            $pageNamespaces = $panel->getPageNamespaces();

            $namespace = (count($pageNamespaces) > 1) ?
                select(
                    label: 'Which namespace would you like to create this in?',
                    options: $pageNamespaces
                ) :
                (Arr::first($pageNamespaces) ?? 'App\\Filament\\Pages');
            $path = (count($pageDirectories) > 1) ?
                $pageDirectories[array_search($namespace, $pageNamespaces)] :
                (Arr::first($pageDirectories) ?? app_path('Filament/Pages/'));
        } else {
            $resourceDirectories = $panel->getResourceDirectories();
            $resourceNamespaces = $panel->getResourceNamespaces();

            $resourceNamespace = (count($resourceNamespaces) > 1) ?
                select(
                    label: 'Which namespace would you like to create this in?',
                    options: $resourceNamespaces
                ) :
                (Arr::first($resourceNamespaces) ?? 'App\\Filament\\Resources');
            $resourcePath = (count($resourceDirectories) > 1) ?
                $resourceDirectories[array_search($resourceNamespace, $resourceNamespaces)] :
                (Arr::first($resourceDirectories) ?? app_path('Filament/Resources/'));
        }

        $view = str($page)
            ->prepend(
                (string) str(empty($resource) ? "{$namespace}\\" : "{$resourceNamespace}\\{$resource}\\pages\\")
                    ->replaceFirst('App\\', '')
            )
            ->replace('\\', '/')
            ->explode('/')
            ->map(fn ($segment) => Str::lower(Str::kebab($segment)))
            ->implode('.');

        $path = (string) str($page)
            ->prepend('/')
            ->prepend(empty($resource) ? $path : $resourcePath . "\\{$resource}\\Pages\\")
            ->replace('\\', '/')
            ->replace('//', '/')
            ->append('.php');

        $viewPath = resource_path(
            (string) str($view)
                ->replace('.', '/')
                ->prepend('views/')
                ->append('.blade.php'),
        );

        $files = [
            $path,
            ...($resourcePage === 'custom' ? [$viewPath] : []),
        ];

        if (! $this->option('force') && $this->checkForCollision($files)) {
            return static::INVALID;
        }

        $potentialCluster = empty($resource) ? ((string) str($namespace)->beforeLast('\Pages')) : null;
        $clusterAssignment = null;
        $clusterImport = null;

        if (
            filled($potentialCluster) &&
            class_exists($potentialCluster) &&
            is_subclass_of($potentialCluster, Cluster::class)
        ) {
            $clusterAssignment = $this->indentString(PHP_EOL . PHP_EOL . 'protected static ?string $cluster = ' . class_basename($potentialCluster) . '::class;');
            $clusterImport = "use {$potentialCluster};" . PHP_EOL;
        }

        if (empty($resource)) {
            $this->copyStubToApp('Page', $path, [
                'class' => $pageClass,
                'clusterAssignment' => $clusterAssignment,
                'clusterImport' => $clusterImport,
                'namespace' => $namespace . ($pageNamespace !== '' ? "\\{$pageNamespace}" : ''),
                'view' => $view,
            ]);
        } elseif ($resourcePage === 'ManageRelatedRecords') {
            $this->copyStubToApp('ResourceManageRelatedRecordsPage', $path, [
                'baseResourcePage' => "Filament\\Resources\\Pages\\{$resourcePage}",
                'baseResourcePageClass' => $resourcePage,
                'modifyQueryUsing' => filled($modifyQueryUsing ?? null) ? PHP_EOL . $this->indentString($modifyQueryUsing, 3) : $modifyQueryUsing ?? '',
                'namespace' => "{$resourceNamespace}\\{$resource}\\Pages" . ($pageNamespace !== '' ? "\\{$pageNamespace}" : ''),
                'recordTitleAttribute' => $recordTitleAttribute ?? null,
                'relationship' => $relationship ?? null,
                'resource' => "{$resourceNamespace}\\{$resource}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $pageClass,
                'tableActions' => $this->indentString($tableActions ?? '', 4),
                'tableBulkActions' => $this->indentString($tableBulkActions ?? '', 5),
                'tableFilters' => $this->indentString(
                    ($hasSoftDeletes ?? false) ? 'Tables\Filters\TrashedFilter::make()' : '//',
                    4,
                ),
                'tableHeaderActions' => $this->indentString($tableHeaderActions ?? '', 4),
                'title' => Str::headline($relationship ?? ''),
                'view' => $view,
            ]);
        } else {
            $this->copyStubToApp($resourcePage === 'custom' ? 'CustomResourcePage' : 'ResourcePage', $path, [
                'baseResourcePage' => 'Filament\\Resources\\Pages\\' . ($resourcePage === 'custom' ? 'Page' : $resourcePage),
                'baseResourcePageClass' => $resourcePage === 'custom' ? 'Page' : $resourcePage,
                'namespace' => "{$resourceNamespace}\\{$resource}\\Pages" . ($pageNamespace !== '' ? "\\{$pageNamespace}" : ''),
                'resource' => "{$resourceNamespace}\\{$resource}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $pageClass,
                'view' => $view,
            ]);
        }

        if (empty($resource) || $resourcePage === 'custom') {
            $this->copyStubToApp('PageView', $viewPath);
        }

        $this->components->info("Filament page [{$path}] created successfully.");

        if ($resource !== null) {
            $this->components->info("Make sure to register the page in `{$resourceClass}::getPages()`.");
        }

        return static::SUCCESS;
    }

}
