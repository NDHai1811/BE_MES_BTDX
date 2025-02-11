<?php

namespace App\Exports\MasterData;

use App\Helpers\Utilities;
use App\Models\Equipment;
use App\Models\Machine;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TestCriteriaExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    private $rowNumber = 0;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->data; // Dữ liệu xuất ra file
    }

    public function headings(): array
    {
        return [
            'STT',
            'Công đoạn',
            'Mã tiêu chí',
            'Tên tiêu chí',
            'Hạng mục',
            'Chỉ tiêu',
            'Dung sai',
            'Phán định',
            'Nguyên tắc',
        ];
    }

    public function map($record): array
    {
        $this->rowNumber++;
        return [
            $this->rowNumber,
            $record->line->name ?? null,
            $record->id,
            $record->name,
            $record->hang_muc,
            $record->chi_tieu,
            $record->tieu_chuan,
            $record->phan_dinh,
            $record->nguyen_tac,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Apply styles to the header row
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '000000'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFF2CC'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Apply border style to all cells
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow())->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
    }
}
