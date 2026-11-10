<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Enums\SeoAbility;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Services\SeoProfiles;
use Nvl\Seo\Services\SeoReader;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Seo\Support\SeoModelIdentifier;
use Nvl\Seo\Support\SeoScope;
use Nvl\Seo\Tests\Fixtures\SeoManagementInput;
use Nvl\Seo\Tests\Fixtures\SeoProfilePresenter;
use Nvl\Support\Enums\SystemActor;

/**
 * Optional authorized, headless profile management API.
 */
final class SeoManagementController extends Controller
{
    public function __construct(
        private readonly SeoAuthorization $authorization,
        private readonly SeoOwnerRegistry $owners,
        private readonly SeoProfilePresenter $presenter,
    ) {}

    /**
     * Return a paginated management list of profiles.
     */
    public function index(
        Request $request,
        SeoReader $action,
    ): JsonResponse {
        $query = SeoManagementInput::profiles($request->query());
        $profiles = $action->paginate(SystemActor::Background, $query);
        $items = [];

        foreach ($profiles->items() as $profile) {
            $items[] = $this->stringKeyed(
                $this->presenter->present($profile)->toArray(),
            );
        }

        return response()->json([
            'data' => [
                'items' => $items,
                'meta' => [
                    'currentPage' => $profiles->currentPage(),
                    'lastPage' => $profiles->lastPage(),
                    'per_page' => $profiles->perPage(),
                    'total' => $profiles->total(),
                ],
            ],
        ]);
    }

    /**
     * Return one authorized management profile.
     */
    public function show(string $profile, SeoReader $action): JsonResponse
    {
        return response()->json([
            'data' => $this->presenter->present($action->get(SystemActor::Background, $profile))->toArray(),
        ]);
    }

    /**
     * Create a profile for one authorized registered owner.
     */
    public function store(
        Request $request,
        SeoProfiles $action,
    ): JsonResponse {
        $data = SeoManagementInput::store($request->all());
        $ownerAlias = $data['ownerAlias'];
        $owner = $this->owners->resolve($ownerAlias, SeoModelIdentifier::normalize($data['ownerId']));
        $scope = SeoScope::normalize(($data['scope'] ?? null));
        $this->authorization->authorize(new SeoAuthorizationContext(actor: SystemActor::Background,
            ability: SeoAbility::Create,
            owner: $owner,
            ownerAlias: $ownerAlias,
            scope: $scope,
        ));
        $profile = $action->sync(SystemActor::Background,
            $owner,
            $data['profile'],
            $scope,
        );

        return response()->json(['data' => $this->presenter->present($profile)->toArray()], 201);
    }

    /**
     * Update one profile with a mandatory optimistic revision token.
     */
    public function update(
        Request $request,
        string $profile,
        SeoProfiles $action,
    ): JsonResponse {
        $input = [];

        foreach ($request->all() as $key => $value) {
            if (is_string($key)) {
                $input[$key] = $value;
            }
        }

        $data = SeoManagementInput::update($input);
        $profileModel = $this->profileModel($profile);
        $owner = $this->authorizeProfile(SeoAbility::Update, $profileModel);
        $updated = $action->sync(SystemActor::Background, $owner, $data, $profileModel->scope);

        return response()->json(['data' => $this->presenter->present($updated)->toArray()]);
    }

    /**
     * Duplicate one profile to an authorized target owner.
     */
    public function duplicate(
        Request $request,
        string $profile,
        SeoProfiles $action,
    ): JsonResponse {
        $data = SeoManagementInput::duplicate($request->all());
        $profileModel = $this->profileModel($profile);
        $ownerAlias = $data['ownerAlias'];
        $target = $this->owners->resolve($ownerAlias, SeoModelIdentifier::normalize($data['ownerId']));
        $sourceOwner = $profileModel->seoable()->firstOrFail();
        $sourceOwnerAlias = $this->owners->aliasFor($sourceOwner);
        $scope = SeoScope::normalize(($data['scope'] ?? null));
        $this->authorization->authorize(new SeoAuthorizationContext(actor: SystemActor::Background,
            ability: SeoAbility::Duplicate,
            profile: $profileModel,
            owner: $sourceOwner,
            ownerAlias: $sourceOwnerAlias,
            targetOwner: $target,
            targetOwnerAlias: $ownerAlias,
            scope: $scope,
        ));
        $duplicate = $action->duplicate(SystemActor::Background,
            $profile,
            $target,
            $scope,
            $data['copyPaths'] ?? false,
        );

        return response()->json(['data' => $this->presenter->present($duplicate)->toArray()], 201);
    }

    /**
     * Archive or restore one authorized profile.
     */
    public function archive(
        Request $request,
        string $profile,
        SeoProfiles $action,
    ): JsonResponse {
        $data = SeoManagementInput::archive($request->all());
        $profileModel = $this->profileModel($profile);
        $this->authorizeProfile(SeoAbility::Archive, $profileModel);
        $updated = $action->archive(SystemActor::Background,
            $profile,
            $data['archived'],
            $data['expected_revision'],
        );

        return response()->json(['data' => $this->presenter->present($updated)->toArray()]);
    }

    /**
     * Delete one authorized profile.
     */
    public function destroy(
        Request $request,
        string $profile,
        SeoProfiles $action,
    ): JsonResponse {
        $data = SeoManagementInput::revision($request->all());
        $profileModel = $this->profileModel($profile);
        $this->authorizeProfile(SeoAbility::Delete, $profileModel);

        return response()->json([
            'data' => [
                'deleted' => $action->delete(SystemActor::Background,
                    $profile,
                    $data['expected_revision'],
                ),
            ],
        ]);
    }

    /**
     * Preview resolved metadata for one authorized profile.
     */
    public function preview(
        Request $request,
        string $profile,
        SeoReader $action,
    ): JsonResponse {
        $query = SeoManagementInput::preview($request->query());
        $profileModel = $this->profileModel($profile);
        $this->authorizeProfile(SeoAbility::Preview, $profileModel);

        return response()->json([
            'data' => $action->resolve(SystemActor::Background,
                $profileModel,
                $query['locale'] ?? null,
            ),
        ]);
    }

    /**
     * Return aggregate status for one authorized scope.
     */
    public function status(
        Request $request,
        SeoProfiles $action,
    ): JsonResponse {
        $query = SeoManagementInput::status($request->query());
        $scope = $query['scope'] ?? null;
        $this->authorization->authorize(new SeoAuthorizationContext(actor: SystemActor::Background,
            ability: SeoAbility::List,
            scope: $scope === null ? null : SeoScope::normalize($scope),
        ));

        return response()->json([
            'data' => $action->status(SystemActor::Background, $scope),
        ]);
    }

    /**
     * Authorize a profile operation with its registered owner identity.
     */
    private function authorizeProfile(SeoAbility $ability, SeoProfile $profile): Model
    {
        $owner = $profile->seoable()->firstOrFail();
        $this->authorization->authorize(new SeoAuthorizationContext(actor: SystemActor::Background,
            ability: $ability,
            profile: $profile,
            owner: $owner,
            ownerAlias: $this->owners->aliasFor($owner),
            scope: $profile->scope,
        ));

        return $owner;
    }

    /**
     * Load one profile model for mutation and preview authorization contexts.
     */
    private function profileModel(string $profile): SeoProfile
    {
        return SeoProfile::query()
            ->with('translations')
            ->findOrFail($profile);
    }

    /**
     * Normalize one Data payload into its documented string-keyed shape.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeyed(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
