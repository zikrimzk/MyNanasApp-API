<?php

namespace App\Http\Controllers;

use App\Models\Premise;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Exception;

class PremiseController extends Controller
{
    private function sendResponse($data, $message, $status = true, $code = 200)
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public function getPremises(Request $request)
    {
        // Implementation for retrieving posts
        $request->validate([
            'premise_type' => 'required|in:All,Announcement,Community',
            'specific_user' => 'nullable|boolean',
        ]);

        try {
            $user = auth()->user(); // Get current logged in user

            // Start the query
            $query = Premise::where('premise_status', 1)
                ->with('user'); // Load the owner of the premise

            // Apply Filters
            if ($request->specific_user) {
                $query->where('entID', $user->entID);
            } else {
                if ($request->premise_type !== 'All') {
                    $query->where('premise_type', $request->premise_type);
                }
            }

            // Get the results
            $premises = $query->get();

            return $this->sendResponse([], 'Premises retrieved successfully');

        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to retrieve premises', false, 500);
        }
    }

    public function addPremise(Request $request)
    {
        $request->validate([
            'premise_type' => 'required|in:Farm,Shop',
            'premise_name' => 'required|string',
            'premise_address' => 'nullable|string',
            'premise_city' => 'nullable|string',
            'premise_state' => 'nullable|string',
            'premise_postcode' => 'nullable|string',
            'premise_landsize' => 'nullable|string',
            'premise_coordinates' => 'nullable|string',
        ]);

        try {
            $user = auth()->user();

            $premise = Premise::create([
                'entID' => $user->entID,
                'premise_type' => $request->premise_type,
                'premise_name' => $request->premise_name,
                'premise_address' => $request->premise_address,
                'premise_city' => $request->premise_city,
                'premise_state' => $request->premise_state,
                'premise_postcode' => $request->premise_postcode,
                'premise_landsize' => $request->premise_landsize,
                'premise_coordinates' => $request->premise_coordinates,
            ]);

            return $this->sendResponse($premise, 'Premise added successfully', true, 201);

        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to add premise', false, 500);
        }
    }

    public function updatePremise(Request $request)
    {
        $request->validate([
            'premiseID' => 'required|exists:premises,premiseID',
            'premise_type' => 'required|in:Farm,Shop',
            'premise_name' => 'required|string',
            'premise_address' => 'nullable|string',
            'premise_city' => 'nullable|string',
            'premise_state' => 'nullable|string',
            'premise_postcode' => 'nullable|string',
            'premise_landsize' => 'nullable|string',
            'premise_coordinates' => 'nullable|string',
            'is_delete' => 'required|boolean', // true for delete, false for update
        ]);

        try {
            $user = auth()->user();
            
            $premise = Premise::find($request->premiseID);

            if($premise->entID !== $user->entID) {
                return $this->sendResponse(null, 'Unauthorized action on this premise', false, 403);
            }

            if ($request->is_delete) {
                $premise->premise_status = 0;
                $premise->updated_at = now();
                $premise->save();
                return $this->sendResponse(null, 'Premise deleted successfully', true, 200);
            } else {
                $premise->premise_type = $request->premise_type;
                $premise->premise_name = $request->premise_name;
                $premise->premise_address = $request->premise_address;
                $premise->premise_city = $request->premise_city;
                $premise->premise_state = $request->premise_state;
                $premise->premise_postcode = $request->premise_postcode;
                $premise->premise_landsize = $request->premise_landsize;
                $premise->premise_coordinates = $request->premise_coordinates;
                $premise->updated_at = now();
                $premise->save();
                return $this->sendResponse($premise, 'Premise updated successfully', true, 200);
            }
        } catch (Exception $e) {
            return $this->sendResponse(null, 'Failed to update/delete premise', false, 500);
        }
    }
}
