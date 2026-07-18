<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AiAgentLogController extends Controller
{
    /**
     * These two pages are UI previews of a planned AI-ops observability layer.
     * There is no agent-orchestration or per-request LLM logging system in the
     * backend yet (only a single-model chat wrapper in App\Services\AI), so no
     * query runs here — every figure on both pages is a static placeholder.
     */
    public function index(Request $request)
    {
        $period = $request->get('period', 'daily');

        return view('backend.layouts.ai_agent_log.index', compact('period'));
    }

    public function transactionCost(Request $request)
    {
        return view('backend.layouts.ai_agent_log.transaction_cost');
    }
}
