<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\MembershipPlan;

class PlanController extends Controller {
    /**
     * Display a listing of membership plans, optionally filtered by name.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        // return response()->json(MembershipPlan::all(), 200);

        // You could paginate here if you have many plans
        // Retrieve the 'name' query parameter (e.g. /api/plans?name=month)
        $name = $request->query('name');

        if (!empty($name)) {
            // If a name filter is provided, apply a case‐insensitive LIKE search
            $plans = MembershipPlan::where('name', 'LIKE', "%{$name}%")->get();
        } else {
            // Otherwise, return all plans as before
            $plans = MembershipPlan::all();
        }

        // Wrap the result in the same JSON structure the frontend expects
        return response()->json([
            'plans' => $plans
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        return response()->json(MembershipPlan::findOrFail($id), 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        //
    }
}
