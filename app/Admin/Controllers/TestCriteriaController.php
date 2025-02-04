<?php

namespace App\Admin\Controllers;

use App\Helpers\QueryHelper;
use App\Models\ErrorLog;
use App\Models\TestCriteria;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Traits\API;
use App\Models\Line;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class TestCriteriaController extends AdminController
{
    use API;

    public static function registerRoutes()
    {
        Route::controller(self::class)->group(function () {
            Route::get('test_criteria/list', [TestCriteriaController::class, 'getTestCriteria']);
            Route::patch('test_criteria/update', [TestCriteriaController::class, 'updateTestCriteria']);
            Route::post('test_criteria/create', [TestCriteriaController::class, 'createTestCriteria']);
            Route::delete('test_criteria/delete', [TestCriteriaController::class, 'deleteTestCriteria']);
            Route::get('test_criteria/export', [TestCriteriaController::class, 'exportTestCriteria']);
            Route::post('test_criteria/import', [TestCriteriaController::class, 'importTestCriteriaVer2']);
        });
    }

    public function import($flag = false)
    {
        $extension = pathinfo($_FILES['files']['name'], PATHINFO_EXTENSION);
        if ($extension == 'csv') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
        } elseif ($extension == 'xlsx') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        } else {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        }
        // file path
        $spreadsheet = $reader->load($_FILES['files']['tmp_name']);
        $allDataInSheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $data = [];
        $line_arr = [];
        $lines = Line::all();
        foreach ($lines as $line) {
            $line_arr[Str::slug($line->name)] = $line->id;
        }
        foreach ($allDataInSheet as $key => $row) {
            //Lấy dứ liệu từ dòng thứ 2
            if ($key > 2) {
                $input = [];
                if (isset($line_arr[Str::slug($row['A'])])) {
                    $input['line_id'] = $line_arr[Str::slug($row['A'])];
                }
                $input['hang_muc'] = $row['B'];
                $input['chi_tieu'] = $row['C'];
                $input['tieu_chuan'] = $row['D'] ?? " ";
                $input['phan_dinh'] = $row['E'];
                $input['reference'] = isset($line_arr[Str::slug($row['F'])]) ? $line_arr[Str::slug($row['F'])] : '';
                $validated = TestCriteria::validateUpdate($input);
                if ($validated->fails()) {
                    return $this->failure('', 'Lỗi dòng thứ ' . ($key) . ': ' . $validated->errors()->first());
                }
                $data[] = $input;
            }
        }
        foreach ($data as $key => $input) {
            $test_criteria = TestCriteria::where('line_id', $input['line_id'])->where('hang_muc', 'like', $input['hang_muc'])->first();
            if ($test_criteria) {
                $test_criteria->update($input);
            } else {
                $test_criteria = TestCriteria::create($input);
            }
        }
        if ($flag) return true;
        admin_success('Tải lên thành công', 'success');
        return back();
    }

    public function getTestCriteria(Request $request)
    {
        $query = TestCriteria::with('line')->orderBy('id')->whereNotNull('hang_muc')->where('hang_muc', '!=', '');
        if (isset($request->line)) {
            $query->where('line_id', $request->line_id);
        }
        if (isset($request->hang_muc)) {
            $query->where('hang_muc', 'like', "%$request->hang_muc%");
        }
        $records = $query->paginate($request->pageSize ?? null);
        $test_criterias = $records->items();
        foreach ($test_criterias as $key => $test_criteria) {
            $test_criteria->line_name  = $test_criteria->line->name ?? "";
        }
        return $this->success(['data' => $test_criterias, 'pagination' => QueryHelper::pagination($request, $records)]);
    }
    public function updateTestCriteria(Request $request)
    {
        $input = $request->all();
        $validated = TestCriteria::validate($input);
        if ($validated->fails()) {
            return $this->failure('', $validated->errors()->first());
        }
        $test_criteria = TestCriteria::where('id', $input['id'])->first();
        if ($test_criteria) {
            $update = $test_criteria->update($input);
            return $this->success($test_criteria);
        } else {
            return $this->failure('', 'Không tìm thấy chỉ tiêu');
        }
    }

    public function createTestCriteria(Request $request)
    {
        $input = $request->all();
        $validated = TestCriteria::validate($input);
        if ($validated->fails()) {
            return $this->failure('', $validated->errors()->first());
        }
        $test_criteria = TestCriteria::create($input);
        return $this->success($test_criteria, 'Tạo thành công');
    }

    public function deleteTestCriteria(Request $request)
    {
        $input = $request->all();
        TestCriteria::whereIn('id', $input)->delete();
        return $this->success('Xoá thành công');
    }

    public function exportTestCriteria(Request $request)
    {
        $line_arr = [];
        $lines = Line::all();
        foreach ($lines as $line) {
            $line_arr[Str::slug($line->name)] = $line->id;
        }
        $query = TestCriteria::with('line')->orderBy('id');
        if (isset($request->line)) {
            $query->where('line_id', $line_arr[Str::slug($request->line)]);
        }
        if (isset($request->hang_muc)) {
            $query->where('hang_muc', 'like', "%$request->hang_muc%");
        }
        $test_criterias = [];
        foreach ($query->get() as $key => $test_criteria) {
            if (str_replace(' ', '', $test_criteria->hang_muc) === "") {
                continue;
            }
            if ($test_criteria->line_id == 38) {
                $test_criteria->line_name  = "IQC";
            } else {
                $test_criteria->line_name  = $test_criteria->line->name ?? "";
            }
            $test_criteria->ref_line_name  = $test_criteria->ref_line ? $test_criteria->ref_line->name : '';
            $test_criterias[] = $test_criteria->toArray();
        }
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $start_row = 2;
        $start_col = 1;
        $centerStyle = [
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => array(
                'outline' => array(
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => array('argb' => '000000'),
                ),
            ),
        ];
        $headerStyle = array_merge($centerStyle, [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => array('argb' => 'BFBFBF')
            ]
        ]);
        $titleStyle = array_merge($centerStyle, [
            'font' => ['size' => 16, 'bold' => true],
        ]);
        $border = [
            'borders' => array(
                'allBorders' => array(
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => array('argb' => '000000'),
                ),
            ),
        ];
        $header = ['Công đoạn', 'Mã lỗi', 'Tiêu chí', 'Hạng mục', 'Chỉ tiêu', 'Dung sai', 'Phân định', 'Tham chiếu TCKT công đoạn'];
        $table_key = [
            'A' => 'line_name',
            'B' => 'id',
            'C' => 'name',
            'D' => 'hang_muc',
            'E' => 'chi_tieu',
            'F' => 'tieu_chuan',
            'G' => 'phan_dinh',
            'H' => 'ref_line_name',
        ];
        foreach ($header as $key => $cell) {
            if (!is_array($cell)) {
                $sheet->setCellValue([$start_col, $start_row], $cell)->mergeCells([$start_col, $start_row, $start_col, $start_row])->getStyle([$start_col, $start_row, $start_col, $start_row])->applyFromArray($headerStyle);
            }
            $start_col += 1;
        }

        $sheet->setCellValue([1, 1], 'Quản lý thông số sản phẩm')->mergeCells([1, 1, $start_col - 1, 1])->getStyle([1, 1, $start_col - 1, 1])->applyFromArray($titleStyle);
        $sheet->getRowDimension(1)->setRowHeight(40);
        $table_col = 1;
        $table_row = $start_row + 1;
        foreach ($test_criterias as $key => $row) {
            $table_col = 1;
            $row = (array)$row;
            foreach ($table_key as $k => $value) {
                if (isset($row[$value])) {
                    $sheet->setCellValue($k . $table_row, $row[$value])->getStyle($k . $table_row)->applyFromArray($centerStyle);
                } else {
                    continue;
                }
                $table_col += 1;
            }
            $table_row += 1;
        }
        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            $sheet->getStyle($column->getColumnIndex() . ($start_row) . ':' . $column->getColumnIndex() . ($table_row - 1))->applyFromArray($border);
        }
        header("Content-Description: File Transfer");
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Chỉ tiêu kiểm tra.xlsx"');
        header('Cache-Control: max-age=0');
        header("Content-Transfer-Encoding: binary");
        header('Expires: 0');
        $writer =  new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('exported_files/Chỉ tiêu kiểm tra.xlsx');
        $href = '/exported_files/Chỉ tiêu kiểm tra.xlsx';
        return $this->success($href);
    }

    public function importTestCriteria(Request $request)
    {
        $extension = pathinfo($_FILES['files']['name'], PATHINFO_EXTENSION);
        if ($extension == 'csv') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
        } elseif ($extension == 'xlsx') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        } else {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        }
        // file path
        $spreadsheet = $reader->load($_FILES['files']['tmp_name']);
        $allDataInSheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $data = [];
        $line_arr = [];
        $lines = Line::all();
        foreach ($lines as $line) {
            $line_arr[Str::slug($line->name)] = $line->id;
        }
        foreach ($allDataInSheet as $key => $row) {
            //Lấy dứ liệu từ dòng thứ 2
            if ($key > 1) {
                $input = [];
                if (!$row['C'] || !$row['D'] || !$row['A']) {
                    continue;
                }
                if (isset($line_arr[Str::slug($row['A'])])) {
                    $input['line_id'] = $line_arr[Str::slug($row['A'])];
                }
                $input['id'] = $row['C'];
                $input['name'] = $row['D'];
                $input['phan_loai'] = Str::slug($row['B']);
                $input['popup_input'] = $row['F'];
                $input['popup_select'] = $row['E'];
                $input['tieu_chuan'] = $row['H'];
                $input['nguyen_tac'] = $row['I'];
                $input['master_data'] = $row['J'];
                $input['ghi_chu'] = $row['K'];
                $validated = TestCriteria::validateUpdate($input);
                if ($validated->fails()) {
                    return $this->failure('', 'Lỗi dòng thứ ' . ($key) . ': ' . $validated->errors()->first());
                }
                $data[] = $input;
            }
        }
        try {
            DB::beginTransaction();
            TestCriteria::where('id', 'not like', 'S%')->where('id', 'not like', 'I%')->delete();
            foreach ($data as $key => $input) {
                // $test_criteria = TestCriteria::where('line_id', $input['line_id'])->where('phan_loai', $input['phan_loai'])->where('name', $input['name'])->first();
                // if($test_criteria) {
                //     $test_criteria->update($input);
                // }else{
                $test_criteria = TestCriteria::create($input);
                // }
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            $err = ErrorLog::saveError($request, $th);
            return $this->failure($err, 'Đã xảy ra lỗi');
        }
        return $this->success([], 'Upload thành công');
    }
    public function importTestCriteriaVer2(Request $request)
    {
        $extension = pathinfo($_FILES['files']['name'], PATHINFO_EXTENSION);
        if ($extension == 'csv') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
        } elseif ($extension == 'xlsx') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        } else {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        }
        // file path
        $spreadsheet = $reader->load($_FILES['files']['tmp_name']);
        $allDataInSheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $data = [];
        $line_arr = [];
        $lines = Line::all();
        foreach ($lines as $line) {
            $line_arr[Str::slug($line->name)] = $line->id;
        }
        foreach ($allDataInSheet as $key => $row) {
            //Lấy dứ liệu từ dòng thứ 2
            if ($key > 2) {
                $input = [];
                if (!$row['B']) {
                    continue;
                }
                if (isset($line_arr[Str::slug($row['B'])])) {
                    $input['line_id'] = $line_arr[Str::slug($row['B'])];
                }
                $input['id'] = $row['D'];
                $input['name'] = $row['E'];
                $input['tieu_chuan'] = $row['H'];
                $input['nguyen_tac'] = $row['I'];
                $input['phan_dinh'] = $row['F'];
                $input['hang_muc'] = $row['C'] === 'Tính năng' ? 'tinh_nang' : 'ngoai_quan';
                $validated = TestCriteria::validateUpdate($input);
                if ($validated->fails()) {
                    return $this->failure('', 'Lỗi dòng thứ ' . ($key) . ': ' . $validated->errors()->first());
                }
                $data[] = $input;
            }
        }
        try {
            DB::beginTransaction();
            TestCriteria::query()->where('id', 'not like', 'I%')->delete();
            foreach ($data as $key => $input) {
                // $test_criteria = TestCriteria::where('line_id', $input['line_id'])->where('phan_loai', $input['phan_loai'])->where('name', $input['name'])->first();
                // if($test_criteria) {
                //     $test_criteria->update($input);
                // }else{
                $test_criteria = TestCriteria::create($input);
                // }
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            $err = ErrorLog::saveError($request, $th);
            return $this->failure($err, 'Đã xảy ra lỗi');
        }
        return $this->success([], 'Upload thành công');
    }
}
