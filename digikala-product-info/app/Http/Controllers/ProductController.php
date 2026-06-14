<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function getProductInfo(Request $request)
    {
        // Get history from session
        $productsHistory = session('product_history', []);
        $errorMessage = null;

        if ($request->isMethod('POST')) {
            // Clear history if requested
            if ($request->has('clear_history')) {
                session()->forget('product_history');
                return redirect()->route('get_product_info');
            }

            // Validate the form
            $request->validate([
                'product_link' => 'required|url'
            ], [
                'product_link.required' => 'لطفاً لینک محصول را وارد کنید',
                'product_link.url' => 'لطفاً یک لینک معتبر وارد کنید'
            ]);

            $link = $request->input('product_link');
            preg_match('/product\/(?:dkp-)?(\d+)/', $link, $matches);

            if (!isset($matches[1])) {
                $errorMessage = "URL معتبر نیست (شناسه محصول یافت نشد)!";
            } else {
                $productId = $matches[1];
                $apiUrl = "https://api.digikala.com/v2/product/{$productId}/";

                try {
                    $response = Http::withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ])->timeout(10)->get($apiUrl);

                    if ($response->successful()) {
                        $data = $response->json();
                        $product = $data['data']['product'] ?? [];
                        $variants = $product['variants'] ?? [];

                        // Brand extraction
                        $brandName = 'نامشخص';
                        if (!empty($variants)) {
                            $firstVariant = $variants[0];
                            $brandInfo = $firstVariant['brand'] ?? null;
                            if (is_array($brandInfo)) {
                                $brandName = $brandInfo['title_en'] ?? $brandInfo['name'] ?? 'نامشخص';
                            } elseif ($brandInfo) {
                                $brandName = $brandInfo;
                            } else {
                                $productBrand = $product['brand'] ?? null;
                                if (is_array($productBrand)) {
                                    $brandName = $productBrand['title_en'] ?? 'نامشخص';
                                } elseif ($productBrand) {
                                    $brandName = $productBrand;
                                }
                            }
                        }

                        // Specifications
                        $specs = $product['specifications'] ?? $product['attributes'] ?? [];
                        $internalStorage = $this->extractSpecValue($specs, 'حافظه داخلی');
                        $ram = $this->extractSpecValue($specs, 'مقدار رم');

                        // Prices from sellers
                        $sellersInfo = [];
                        foreach ($variants as $variant) {
                            $sellerPrice = $variant['price']['rrp_price'] ?? null;
                            if ($sellerPrice) {
                                $sellersInfo[] = ['price' => $sellerPrice];
                            }
                        }

                        $minPrice = null;
                        $maxPrice = null;
                        if (!empty($sellersInfo)) {
                            $prices = array_column($sellersInfo, 'price');
                            $minPrice = min($prices);
                            $maxPrice = max($prices);
                        }

                        $defaultVariant = $product['default_variant'] ?? [];
                        $titleEn = $product['title_en'] ?? '';
                        if (empty($titleEn)) {
                            $titleEn = $product['title_fa'] ?? '';
                        }

                        $productData = [
                            'category' => $product['data_layer']['category'] ?? '',
                            'brand' => $brandName,
                            'title_en' => $titleEn,
                            'internal_storage' => $internalStorage ?? '',
                            'storage' => $ram ?? '',
                            'min_seller_price' => $minPrice,
                            'max_seller_price' => $maxPrice,
                            'warranty' => $defaultVariant['warranty']['title_fa'] ?? null,
                        ];

                        // Add to session history
                        $productsHistory[] = ['product_data' => $productData];
                        session(['product_history' => $productsHistory]);

                        // Clear any error
                        $errorMessage = null;
                    } else {
                        $errorMessage = "خطا در دریافت اطلاعات: " . $response->status();
                    }
                } catch (\Exception $e) {
                    $errorMessage = "اتصال به API ناموفق: " . $e->getMessage();
                }
            }
        }

        return view('product_info.index', [
            'products_history' => $productsHistory,
            'error_message' => $errorMessage,
        ]);
    }

    private function extractSpecValue($specs, $targetTitle)
    {
        if (!is_array($specs)) {
            return null;
        }
        foreach ($specs as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['title']) && $item['title'] == $targetTitle) {
                $values = $item['values'] ?? [];
                if (is_array($values) && count($values) > 0) {
                    return $values[0];
                }
                return null;
            }
            if (isset($item['attributes']) && is_array($item['attributes'])) {
                $result = $this->extractSpecValue($item['attributes'], $targetTitle);
                if ($result) {
                    return $result;
                }
            }
        }
        return null;
    }

    public function exportToExcel()
    {
        $productsHistory = session('product_history', []);
        if (empty($productsHistory)) {
            return redirect()->route('get_product_info')->with('error', 'هیچ اطلاعاتی برای خروجی وجود ندارد!');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('اطلاعات محصولات');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
        $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2A5298');
        $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $headers = [
            'id', 'دسته بندی', 'برند', 'عنوان', 'مقدار رم', 'حافظه داخلی', 'پایینترین قیمت', 'بالاترین قیمت', 'گارانتی',
        ];
        foreach ($headers as $colIndex => $header) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, 1, $header);
        }

        $row = 2;
        foreach ($productsHistory as $index => $entry) {
            $data = $entry['product_data'];
            $sheet->setCellValueByColumnAndRow(1, $row, $row - 1);
            $sheet->setCellValueByColumnAndRow(2, $row, $data['category'] ?? '');
            $sheet->setCellValueByColumnAndRow(3, $row, $data['brand'] ?? '');
            $sheet->setCellValueByColumnAndRow(4, $row, $data['title_en'] ?? '');
            $sheet->setCellValueByColumnAndRow(5, $row, $data['storage'] ?? '');
            $sheet->setCellValueByColumnAndRow(6, $row, $data['internal_storage'] ?? '');
            $sheet->setCellValueByColumnAndRow(7, $row, $data['min_seller_price'] ?? '');
            $sheet->setCellValueByColumnAndRow(8, $row, $data['max_seller_price'] ?? '');
            $sheet->setCellValueByColumnAndRow(9, $row, $data['warranty'] ?? '');
            $row++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="digikala_products.xlsx"');
        return $response;
    }
}