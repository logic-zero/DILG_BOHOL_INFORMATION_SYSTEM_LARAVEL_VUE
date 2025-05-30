<?php

namespace App\Http\Controllers;

use App\Models\Provincial_Official;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminProvincial_OfficialsController extends Controller
{
    public function index(Request $request)
    {
        $query = Provincial_Official::query();

        if ($request->filled('position')) {
            $query->where('position', $request->position);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%$search%");
        }

        $officials = $query->get();

        return Inertia::render('Admin/AdminProvOfficials', [
            'officials' => $officials,
            'filters' => $request->only(['search', 'position']),
            'positions' => [
                'Governor',
                'Vice Governor',
                'Member, 1st District',
                'Member, 2nd District',
                'Member, 3rd District',
                'President PCL Bohol Federation',
                'Liga ng mga Barangay',
                'SK Federation President',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'name' => 'required|string',
            'position' => 'required|string',
        ]);

        $uploadPath = public_path('provincial_officials');
        File::ensureDirectoryExists($uploadPath);

        if ($request->hasFile('profile_image')) {
            $fileName = Str::random(20) . '.' . $request->file('profile_image')->extension();
            $request->file('profile_image')->move($uploadPath, $fileName);
            $validated['profile_image'] = $fileName;
        }

        Provincial_Official::create($validated);

        return redirect()->route('AdminProvincialOfficials')->with('success', 'Provincial official added successfully.');
    }

    public function update(Request $request, Provincial_Official $provincial_official)
    {
        $validated = $request->validate([
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'name' => 'required|string',
            'position' => 'required|string',
        ]);

        $uploadPath = public_path('provincial_officials');

        if ($request->hasFile('profile_image')) {
            if ($provincial_official->profile_image) {
                $filePath = public_path('provincial_officials/' . $provincial_official->profile_image);
                if (File::exists($filePath)) {
                    File::delete($filePath);
                }
            }
            $fileName = Str::random(20) . '.' . $request->file('profile_image')->extension();
            $request->file('profile_image')->move($uploadPath, $fileName);
            $validated['profile_image'] = $fileName;
        } elseif ($request->has('remove_image')) {
            if ($provincial_official->profile_image) {
                $filePath = public_path('provincial_officials/' . $provincial_official->profile_image);
                if (File::exists($filePath)) {
                    File::delete($filePath);
                }
            }
            $validated['profile_image'] = null;
        }

        $provincial_official->update($validated);

        return redirect()->route('AdminProvincialOfficials')->with('success', 'Provincial official updated successfully.');
    }

    public function destroy(Provincial_Official $provincial_official)
    {
        if ($provincial_official->profile_image) {
            $filePath = public_path('provincial_officials/' . $provincial_official->profile_image);
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        }

        $provincial_official->delete();

        return redirect()->route('AdminProvincialOfficials')->with('success', 'Provincial official deleted successfully.');
    }
}
