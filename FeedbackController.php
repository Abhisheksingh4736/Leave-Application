<?php

namespace App\Http\Controllers\admincpapi;

use App\Models\User;
use App\Models\Feedback;
use App\Models\Prompt;
use App\Helpers\Utility;
use App\Models\AdminUser;
use App\Service\FileService;
use App\Service\FeedbackService;
use App\Enum\ActiveStatusEnum;
use App\Enum\IsPreviewEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Service\CountryService;
use App\Http\Requests\admincp\FeedbackStoreRequest;
use App\Http\Requests\admincp\FeedbackUpdateRequest;
use App\DataTables\FeedbackDataTable;
use App\Http\Resources\Feedback\FeedbackCollection;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;

class FeedbackController extends Controller
{
    private FileService $fileService;

    private CountryService $countryService;

    public function FeedbackStore(Request $request)
    {
       $user = Auth::user();
       DB::beginTransaction();

       try {

           // $pdfPath = null;
           // if ($request->hasFile('pdf')) {
           //     $pdf = $request->file('pdf');
           //     $pdfPath = uniqid() . '.' . $pdf->getClientOriginalExtension();
           //     $pdfPath = $pdf->storeAs('public/feedback', $pdfName);
           //     $pdfUrl = asset('storage/pdf/' . $pdfName);
           // } else {
           //     $imageUrl = null;
           // }

           // $imagePath = null;
           // if ($request->hasFile('image')) {
           //     $image = $request->file('image');
           //     $imageName = uniqid() . '.' . $image->getClientOriginalExtension();
           //     $imagePath = $image->storeAs('public/feedback', $imageName);
           //     $imageUrl = asset('storage/image/' . $imageName);
           // } else {
           //     $imageUrl = null;
           // }

           $data = [
               'feedback_topic'       => $request->feedback_topic,
               'upload_file_type'     => $request->upload_file_type,
               // 'pdf'                  =>$item->pdf ? asset(env('STORAGE_URL').'/feedback/' . $item->pdf) : null,
               // 'image'                =>$item->image ? asset(env('STORAGE_URL').'/feedback/' . $item->image) : null,
               'prompt_id'            => $request->prompt_id,
               'board_id'            => $request->board_id,
               'medium_id'            => $request->medium_id,
               'standard_id'            => $request->standard_id,
               'subject_id'            => $request->subject_id,
               'dropdown_id'            => $request->dropdown_id,
               'is_active'            => $request->status ?? ActiveStatusEnum::Active->value
           ];
           // dd($requestData);
         $feedback = Feedback::create($data);

           DB::commit();
           return response()->json([
               'status'  => 'success',
               'data'    => $data,
               'message' => "Feedback Stored Successfully."
           ], 200);

       } catch (\Exception $exception) {
           // dd($exception);
           DB::rollback();

           return response()->json([
               'status'  => 'error',
               'message' => 'Oops! Something went wrong. Please try again.',
               'error'   => $exception->getMessage()
           ], 500);
       }
   }


    public function FeedbackList(Request $request)
    { 
        $query = Feedback::with('prompt');

        if ($request->filled('status'))
        {
            $query->where('is_active', $request->status);
        }
        if ($request->filled('feedback_topic'))
        {
            $query->where('feedback_topic', 'LIKE', "%" . $request->feedback_topic . "%");
        }
        if ($request->filled('upload_file_type'))
        {
            $query->where('upload_file_type', 'LIKE', "%" . $request->upload_file_type . "%");
        }
        if ($request->filled('prompt_id'))
        {
            $query->where('prompt_id', $request->input('prompt_id'));
        }
        $data = $query->orderBy('id', 'desc')->get();


        $feedback_array = array();
        if (is_object($data)) {
            foreach ($data as $key => $data1) {
                $feedback_array[] = $data[$key];
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
       
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($feedback_array[$offset])) {
                       $feedback = $feedback_array[$offset];
                       $data_array[] = [
                                'id'                 =>$feedback->id,
                                'feedback_topic'     => $feedback->feedback_topic,
                                'upload_file_type'   => $feedback->upload_file_type,
                                'prompt_id'          => $feedback->prompt_id,
                                'prompt_name'        => $feedback->prompt ? $feedback->prompt->name : null,
                                'is_active'          => $feedback->is_active,
                            ];
                 }
                $offset++;
            }
            
        } else {

           $formattedData = $data->map(function ($feedback) {
                       return [
                        'id'                 =>$feedback->id,
                        'feedback_topic'     => $feedback->feedback_topic,
                        'upload_file_type'   => $feedback->upload_file_type,
                        'prompt_id'          => $feedback->prompt_id,
                        'prompt_name'        => $feedback->prompt ? $feedback->prompt->name : null,
                        'is_active'          => $feedback->is_active,
                    ];
                   });
            $data_array = $formattedData;
            
        } 
       
        return response()->json(['status' => true,'message' => 'FeedBack List', 'data' => $data_array,
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => $limit,
                'total_pages' => $limit ? ceil(count($feedback_array) / $limit) : ceil(count($feedback_array)),
                'total_records' => count($feedback_array),
            ]
        ],200);
        
    }

    public function FeedbackUpdate(Request $request, Feedback $feedback) {
        // $data = $request->validate([
        //     'feedback_topic' => 'sometimes|string|max:2000',
        //     'upload_file_type' => 'sometimes|string|max:20',
        //     'prompt_id' => 'sometimes|exists:prompts,id',
        // ]);
        $data = [
            'feedback_topic'    => $request->feedback_topic,
            'upload_file_type'  => $request->upload_file_type,
            'prompt_id'         => $request->prompt_id,
            'board_id'          => $request->board_id,
            'medium_id'         => $request->medium_id,
            'standard_id'       => $request->standard_id,
            'subject_id'        => $request->subject_id,
            'dropdown_id'       => $request->dropdown_id,
            'is_active'         => $request->status ?? ActiveStatusEnum::Active->value
        ];

        $feedback->update($data);
        return response()->json(['status' => 'success','data' => $feedback, 'message' => "Feedback Updated Successfully"], 200);

    }

    public function destroy(Feedback $feedback)
    {
        DB::beginTransaction();
        try {
            if ($feedback->delete()) {
                DB::commit();
                return response()->json(['status' => 'success','data' => $feedback, 'message' => "Feedback delete Successfully"], 200);

            }else{
                DB::rollback();
                return response()->json(['status' => 'error','data' => [],'message' => "Oops!!!, something went wrong, please try again."]);
            }
        } catch (\Exception $exception) {
            DB::rollback();  
        
            return response()->json(['status' => 'error', 'message' => 'Oops!!!, something went wrong, please try again.','error' => $exception->getMessage()]);
        
        } catch (\Throwable $exception) {
        
            DB::rollback();   
        
            return response()->json(['status' => 'error', 'message' => 'Oops!!!, something went wrong, please try again.', 'error' => $exception->getMessage()]);
        }
    }
     
    public function getfeedbackfrontened()
    {
        // $data = Feedback::all();
        $data = Feedback::select('id', 'feedback_topic','upload_file_type', 'is_active')->get();
        if ($data->isNotEmpty()) {
            return response()->json([
                'status'  => 'success',
                'data'    => $data,
                'message' => 'Get Details Successfully',
            ], 200);
        } else {
            return response()->json([
                'status'  => 'error',
                'message' => 'No data found',
            ], 200);
        }
    }
    
    public function getFeedbackBackend($id)
    {
        $data = Feedback::find($id);
    
        if ($data) {
            return response()->json([
                'status'  => 'success',
                'data'    => $data,
                'message' => 'Get Details Successfully',
            ], 200);
        } else {
            return response()->json([
                'status'  => 'error',
                'message' => 'No data found',
            ], 200);
        }
    }
    
//     public function FeedbackStoreFrtonend(Request $request)
//     {
//        DB::beginTransaction();

//        try {
 
//             $user = Auth::user();
            
//             $data = [
//                'created_by'           => $user->id,
//                'feedback_topic'       => $request->feedback_topic,
//                'upload_file_type'     => $request->upload_file_type,
//                'prompt_id'            => $request->prompt_id,
//                'is_active'            => $request->status ?? ActiveStatusEnum::Active->value
//            ];
          
//          $feedback = Feedback::create($data);

//            DB::commit();
//            return response()->json([
//                'status'  => 'success',
//                'data'    => $data,
//                'message' => "Feedback Stored Successfully."
//            ], 200);

//        } catch (\Exception $exception) {
//            // dd($exception);
//            DB::rollback();

//            return response()->json([
//                'status'  => 'error',
//                'message' => 'Oops! Something went wrong. Please try again.',
//                'error'   => $exception->getMessage()
//            ], 500);
//        }
//    }
    
   

   public function multiDeletefeedback(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:feedbacks,id',  
        ]);

        DB::beginTransaction();

        try {
            $deleted = Feedback::whereIn('id', $request->ids)->delete();

            if ($deleted) {
                DB::commit();

                return response()->json([ 
                    'status' => 'success',
                    'message' => "FeedBack deleted successfully"
                ], 200);
            } else {
               
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => "No records found to delete"
                ],200);
            }

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => "An error occurred: " . $e->getMessage()
            ], 500);
        }
    }

        //    public function getfeedbackfrontened_data(Request $request)
        //     {
        //         $page = $request->input('page', 1);
        //         $page_size = $request->input('page_size', 10);
        //         $user = Auth::user();

        //         $data = Feedback::where('created_by',$user->id)->paginate($page_size, ['*'], 'page', $page);
                
        //         if($data->count() > 0)
        //           {
                    
        //             return response()->json(['status' => true, 'data' => $data->items(), 'message' => 'FeedBack Details List', 'errors' => [],
        //                 'pagination' => [
        //                     'total_records' => $data->total(),    
        //                     'total_pages' => $data->lastPage(),    
        //                     'current_page' => $data->currentPage()  
        //                 ]
        //             ]);

        //           }else{
        //             return response()->json(['status' => 'error','data' => [], 'message' => "FeedBack Data Not Found"],200);

        //           }
        //     }


        public function destroyFrontend($id)
    {
        $data = Feedback::find($id);

        if ($data) {
            $data->delete(); // Soft delete (sets deleted_at)

            return response()->json([
                'status' => 'success',
                'message' => 'Feedback Deleted Successfully'
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Feedback not found'
            ], 200);
        }
    }

    //Code For Frontend
    
    public function FeedbackListFrontend(Request $request)
    { 
        $query = Feedback::with('prompt');

        if ($request->filled('status'))
        {
            $query->where('is_active', $request->status);
        }
        if ($request->filled('feedback_topic'))
        {
            $query->where('feedback_topic', 'LIKE', "%" . $request->feedback_topic . "%");
        }
        if ($request->filled('upload_file_type'))
        {
            $query->where('upload_file_type', 'LIKE', "%" . $request->upload_file_type . "%");
        }
        if ($request->filled('prompt_id'))
        {
            $query->where('prompt_id', $request->input('prompt_id'));
        }
        
        if (Auth::check() && Auth::user()->user_type === 'teacher') {
            $query->where('created_by', Auth::id());
        }

        $data = $query->orderBy('id', 'desc')->get();

        $feedback_array = array();
        if (is_object($data)) {
            foreach ($data as $key => $data1) {
                $feedback_array[] = $data[$key];
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
       
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($feedback_array[$offset])) {
                       $feedback = $feedback_array[$offset];
                       $data_array[] = [
                                'id'                 =>$feedback->id,
                                'feedback_topic'     => $feedback->feedback_topic,
                                'upload_file_type'   => $feedback->upload_file_type,
                                'prompt_id'          => $feedback->prompt_id,
                                'prompt_name'        => $feedback->prompt ? $feedback->prompt->name : null,
                                'is_active'          => $feedback->is_active,
                            ];
                 }
                $offset++;
            }
        } else {
           $formattedData = $data->map(function ($feedback) {
                       return [
                        'id'                 =>$feedback->id,
                        'feedback_topic'     => $feedback->feedback_topic,
                        'upload_file_type'   => $feedback->upload_file_type,
                        'prompt_id'          => $feedback->prompt_id,
                        'prompt_name'        => $feedback->prompt ? $feedback->prompt->name : null,
                        'is_active'          => $feedback->is_active,
                    ];
                   });
            $data_array = $formattedData;
            
        } 
        
        return response()->json(['status' => true,'message' => 'FeedBack List', 'data' => $data_array,
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => $limit,
                'total_pages' => $limit ? ceil(count($feedback_array) / $limit) : ceil(count($feedback_array)),
                'total_records' => count($feedback_array),
            ]
        ],200);
    }

    public function FeedbackStoresFrontend(Request $request)
    {
       $user = Auth::user();
       DB::beginTransaction();

       try {
           $data = [
               'feedback_topic'       => $request->feedback_topic,
               'upload_file_type'     => $request->upload_file_type,
               'prompt_id'            => $request->prompt_id,
               'board_id'            => $request->board_id,
               'medium_id'            => $request->medium_id,
               'standard_id'            => $request->standard_id,
               'subject_id'            => $request->subject_id,
               'dropdown_id'            => $request->dropdown_id,
               'is_active'            => $request->status ?? ActiveStatusEnum::Active->value
           ];
           // dd($requestData);
         $feedback = Feedback::create($data);

           DB::commit();
           return response()->json([
               'status'  => 'success',
               'data'    => $data,
               'message' => "Feedback Stored Successfully."
           ], 200);

       } catch (\Exception $exception) {
           // dd($exception);
           DB::rollback();

           return response()->json([
               'status'  => 'error',
               'message' => 'Oops! Something went wrong. Please try again.',
               'error'   => $exception->getMessage()
           ], 500);
       }
   }
    
    public function FeedbackUpdateFrontend(Request $request, $id)  
    {
        $feedback = Feedback::find($id);

        if (!$feedback) {
            return response()->json([
                'status' => 'error',
                'message' => 'Feedback not found.',
                'data' => new \stdClass() // Empty object instead of []
            ], 404);
        }

        $data = [
            'feedback_topic'    => $request->feedback_topic,
            'upload_file_type'  => $request->upload_file_type,
            'prompt_id'         => $request->prompt_id,
            'board_id'          => $request->board_id,
            'medium_id'         => $request->medium_id,
            'standard_id'       => $request->standard_id,
            'subject_id'        => $request->subject_id,
            'dropdown_id'       => $request->dropdown_id,
            'is_active'         => $request->status ?? ActiveStatusEnum::Active->value
        ];

        $feedback->update($data);

        $feedback = Feedback::find($id); 

        return response()->json([
            'status' => 'success',
            'data' => $feedback, // Now it will show updated data
            'message' => "Feedback Updated Successfully"
        ], 200);
    }

   

        public function DestroysFrontend($id)
        {
            DB::beginTransaction();
            try {
                $feedback = Feedback::find($id);

                if (!$feedback) {
                    return response()->json(['status' => 'error', 'message' => 'Feedback not found.'], 404);
                }
                $feedback->delete();

                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'data' => $feedback,
                    'message' => "Feedback deleted successfully"
                
                ], 200);

            } catch (\Exception $exception) {
                DB::rollback();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Oops!!!, something went wrong, please try again.',
                    'error' => $exception->getMessage()
                ], 500);
            }
        }
        

        public function MultiDeleteFeedbackFrontend(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:feedbacks,id',  
        ]);

        DB::beginTransaction();

        try {
            $deleted = Feedback::whereIn('id', $request->ids)->delete();

            if ($deleted) {
                DB::commit();

                return response()->json([ 
                    'status' => 'success',
                    'message' => "FeedBack deleted successfully"
                ], 200);
            } else {
               
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => "No records found to delete"
                ],200);
            }

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => "An error occurred: " . $e->getMessage()
            ], 500);
        }
    }
}
