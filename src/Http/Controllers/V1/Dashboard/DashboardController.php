<?php

namespace Webkul\RestApi\Http\Controllers\V1\Dashboard;

use Illuminate\Http\JsonResponse;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Quote\Repositories\QuoteRepository;
use Webkul\RestApi\Http\Controllers\V1\Controller;
use Webkul\RestApi\Http\Controllers\V1\Concerns\ResolvesAuthorizedUserIds;
use Webkul\RestApi\Http\Resources\V1\Activity\ActivityResource;
use Webkul\User\Repositories\UserRepository;

class DashboardController extends Controller
{
    use ResolvesAuthorizedUserIds;

    public function __construct(
        protected LeadRepository $leadRepository,
        protected PipelineRepository $pipelineRepository,
        protected QuoteRepository $quoteRepository,
        protected ActivityRepository $activityRepository,
        protected PersonRepository $personRepository,
        protected OrganizationRepository $organizationRepository,
        protected ProductRepository $productRepository,
        protected UserRepository $userRepository,
    ) {}

    /**
     * Return the dashboard summary for the authenticated administrator.
     */
    public function stats(): JsonResponse
    {
        $leadQuery = $this->leadRepository->query();

        if ($userIds = $this->getAuthorizedUserIds()) {
            $leadQuery->whereIn('user_id', $userIds);
        }

        $pipeline = $this->pipelineRepository->getDefaultPipeline();
        $stageIds = $pipeline->stages->pluck('id');
        $pipelineLeads = (clone $leadQuery)
            ->where('lead_pipeline_id', $pipeline->id)
            ->whereIn('lead_pipeline_stage_id', $stageIds)
            ->get(['lead_pipeline_stage_id', 'lead_value']);

        $pipelineStats = $pipeline->stages->mapWithKeys(function ($stage) use ($pipelineLeads) {
            $stageLeads = $pipelineLeads->where('lead_pipeline_stage_id', $stage->id);

            return [$stage->name => $stageLeads->count()];
        })->all();

        $wonStageIds = $pipeline->stages->where('code', 'won')->pluck('id');
        $lostStageIds = $pipeline->stages->where('code', 'lost')->pluck('id');

        $recentActivities = $this->activityRepository
            ->resetModel()
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'total_leads' => (clone $leadQuery)->count(),
                'total_quotes' => $this->quoteRepository->resetModel()->count(),
                'total_activities' => $this->activityRepository->resetModel()->count(),
                'total_persons' => $this->personRepository->resetModel()->count(),
                'total_organizations' => $this->organizationRepository->resetModel()->count(),
                'total_products' => $this->productRepository->resetModel()->count(),
                'lead_value' => (float) (clone $leadQuery)->sum('lead_value'),
                'won_leads' => $pipelineLeads->whereIn('lead_pipeline_stage_id', $wonStageIds)->count(),
                'lost_leads' => $pipelineLeads->whereIn('lead_pipeline_stage_id', $lostStageIds)->count(),
                'pipeline_leads' => $pipelineStats,
                'recent_activities' => ActivityResource::collection($recentActivities)->resolve(),
            ],
        ]);
    }
}