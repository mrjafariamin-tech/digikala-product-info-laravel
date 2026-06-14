<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اطلاعات محصول دیجیکالا</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.0.1/css/bootstrap.min.css" />
</head>
<body class="bg-dark">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">دریافت اطلاعات محصول از دیجیکالا</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="product_link" class="form-label">لینک محصول دیجیکالا</label>
                                <input type="url" name="product_link" id="product_link" class="form-control mt-2" placeholder="https://www.digikala.com/product/dkp-123456/" style="direction: ltr; font-family: Tahoma;" required>
                                @error('product_link')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">دریافت اطلاعات</button>
                            <a href="{{ route('export_to_excel') }}" class="btn btn-success btn-sm">خروجی اکسل</a>
                            <button type="submit" class="btn btn-outline-danger btn-sm" name="clear_history" value="1">پاک کردن اطلاعات</button>
                        </form>

                        @if(session('error'))
                            <div class="alert alert-danger mt-4">{{ session('error') }}</div>
                        @endif

                        @if(isset($error_message) && $error_message)
                            <div class="alert alert-danger mt-4">{{ $error_message }}</div>
                        @endif

                        <h5 class="mt-4">اطلاعات محصول</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>دسته بندی</th>
                                        <th>برند</th>
                                        <th>عنوان</th>
                                        <th>مقدار رم</th>
                                        <th>حافظه داخلی</th>
                                        <th>کمترین قیمت</th>
                                        <th>بیشترین قیمت</th>
                                        <th>گارانتی</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($products_history as $entry)
                                        <tr>
                                            <td>{{ $entry['product_data']['category'] ?? '' }}</td>
                                            <td>{{ $entry['product_data']['brand'] ?? '' }}</td>
                                            <td>{{ $entry['product_data']['title_en'] ?? '' }}</td>
                                            <td>{{ $entry['product_data']['storage'] ?? '' }}</td>
                                            <td>{{ $entry['product_data']['internal_storage'] ?? '' }}</td>
                                            <td>
                                                @if(!empty($entry['product_data']['min_seller_price']))
                                                    {{ number_format($entry['product_data']['min_seller_price']) }} تومان
                                                @else
                                                    <span class="text-muted">نامشخص</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!empty($entry['product_data']['max_seller_price']))
                                                    {{ number_format($entry['product_data']['max_seller_price']) }} تومان
                                                @else
                                                    <span class="text-muted">نامشخص</span>
                                                @endif
                                            </td>
                                            <td>{{ $entry['product_data']['warranty'] ?? '' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center">هنوز محصولی ثبت نشده است.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>