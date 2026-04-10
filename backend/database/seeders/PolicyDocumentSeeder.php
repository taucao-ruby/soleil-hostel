<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PolicyDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $verifiedAt = $now;

        $documents = [
            [
                'id' => Str::uuid()->toString(),
                'slug' => 'cancellation-policy',
                'title' => 'Chính sách hủy đặt phòng',
                'content' => <<<'EOT'
# Chính sách hủy đặt phòng — Soleil Hostel

## Hủy miễn phí
- Hủy trước **48 giờ** so với ngày nhận phòng: hoàn tiền **100%**.

## Hủy muộn
- Hủy trong vòng **24–48 giờ** trước ngày nhận phòng: hoàn tiền **50%**.
- Hủy trong vòng **24 giờ** trước ngày nhận phòng hoặc không đến (no-show): **không hoàn tiền**.

## Trường hợp đặc biệt
- Thiên tai, dịch bệnh, hoặc sự kiện bất khả kháng: liên hệ lễ tân để được hỗ trợ xem xét từng trường hợp.
- Đặt phòng nhóm (từ 5 phòng trở lên): áp dụng chính sách hủy riêng, vui lòng liên hệ trước khi đặt.

## Quy trình hoàn tiền
- Hoàn tiền được xử lý trong vòng **5–7 ngày làm việc** qua phương thức thanh toán ban đầu.
- Phí chuyển khoản ngân hàng (nếu có) do khách hàng chịu.

## Liên hệ
- Email: support@soleilhostel.vn
- Hotline: 0909-123-456
EOT,
                'category' => 'cancellation',
                'language' => 'vi',
                'is_active' => true,
                'last_verified_at' => $verifiedAt,
                'version' => '1.0.0',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'slug' => 'checkin-checkout-policy',
                'title' => 'Chính sách nhận phòng và trả phòng',
                'content' => <<<'EOT'
# Chính sách nhận phòng và trả phòng — Soleil Hostel

## Giờ nhận phòng (Check-in)
- Giờ nhận phòng tiêu chuẩn: **14:00** (2:00 PM).
- Nhận phòng sớm (early check-in): tùy tình trạng phòng trống, phụ thu **50.000 VNĐ/giờ**.
- Nhận phòng sau **22:00**: vui lòng thông báo trước để nhân viên hỗ trợ.

## Giờ trả phòng (Check-out)
- Giờ trả phòng tiêu chuẩn: **12:00** (trưa).
- Trả phòng muộn (late check-out): tùy tình trạng phòng, phụ thu **50.000 VNĐ/giờ**, tối đa đến **15:00**.
- Trả phòng sau **15:00** sẽ tính thêm **1 đêm**.

## Thủ tục nhận phòng
- Xuất trình giấy tờ tùy thân có ảnh (CMND/CCCD/Hộ chiếu).
- Đặt cọc: **200.000 VNĐ** (hoàn trả khi trả phòng nếu không có hư hại).
- Ký xác nhận nội quy nhà trọ.

## Liên hệ
- Lễ tân: 0909-123-456 (24/7)
EOT,
                'category' => 'checkin',
                'language' => 'vi',
                'is_active' => true,
                'last_verified_at' => $verifiedAt,
                'version' => '1.0.0',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'slug' => 'house-rules',
                'title' => 'Nội quy nhà trọ',
                'content' => <<<'EOT'
# Nội quy nhà trọ — Soleil Hostel

## Quy định chung
- Giữ yên lặng từ **22:00 đến 07:00**.
- Không hút thuốc trong phòng và khu vực chung có mái che. Khu vực hút thuốc được chỉ định riêng.
- Không mang thú cưng vào khuôn viên hostel.
- Không sử dụng chất cấm hoặc mang vật phẩm nguy hiểm vào hostel.

## An ninh
- Khóa cửa phòng khi ra ngoài. Hostel không chịu trách nhiệm cho vật dụng cá nhân bị mất.
- Sử dụng tủ khóa cá nhân (locker) được cung cấp để bảo quản tài sản quý giá.
- Không cho người lạ vào phòng hoặc khu vực dành riêng cho khách.

## Vệ sinh
- Giữ gìn vệ sinh khu vực chung (bếp, phòng tắm, phòng khách).
- Rửa dụng cụ sau khi nấu ăn.
- Phân loại rác theo hướng dẫn.

## Vi phạm
- Vi phạm nội quy lần đầu: nhắc nhở.
- Vi phạm nghiêm trọng hoặc tái phạm: yêu cầu trả phòng, không hoàn tiền.

## Liên hệ
- Lễ tân: 0909-123-456
EOT,
                'category' => 'rules',
                'language' => 'vi',
                'is_active' => true,
                'last_verified_at' => $verifiedAt,
                'version' => '1.0.0',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'slug' => 'amenities-list',
                'title' => 'Tiện ích và dịch vụ',
                'content' => <<<'EOT'
# Tiện ích và dịch vụ — Soleil Hostel

## Miễn phí
- **Wi-Fi tốc độ cao** toàn khu vực.
- **Nước uống** tại khu vực chung (nước lọc, trà, cà phê).
- **Tủ khóa cá nhân** (locker) trong phòng dorm.
- **Bếp chung** với đầy đủ dụng cụ nấu ăn cơ bản.
- **Phòng khách chung** với TV, sách, trò chơi board game.
- **Sân thượng** (rooftop) ngắm cảnh.
- **Máy giặt** (tự phục vụ, 1 lần/ngày).

## Có phí
- **Giặt ủi**: 30.000 VNĐ/kg.
- **Thuê xe đạp**: 50.000 VNĐ/ngày.
- **Thuê xe máy**: 120.000 VNĐ/ngày (yêu cầu bằng lái).
- **Tour du lịch**: liên hệ lễ tân để biết lịch trình và giá.
- **Đưa đón sân bay**: 200.000 VNĐ/lượt (đặt trước 24 giờ).

## Khu vực chung
- Bếp chung mở cửa: **06:00 – 23:00**.
- Phòng khách: **24/7**.
- Sân thượng: **06:00 – 23:00**.

## Liên hệ
- Lễ tân: 0909-123-456
EOT,
                'category' => 'amenities',
                'language' => 'vi',
                'is_active' => true,
                'last_verified_at' => $verifiedAt,
                'version' => '1.0.0',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'slug' => 'payment-methods',
                'title' => 'Phương thức thanh toán',
                'content' => <<<'EOT'
# Phương thức thanh toán — Soleil Hostel

## Thanh toán trực tuyến
- **Thẻ tín dụng / ghi nợ**: Visa, MasterCard, JCB.
- **Ví điện tử**: MoMo, ZaloPay, VNPay.
- **Chuyển khoản ngân hàng**: thông tin tài khoản sẽ được gửi qua email xác nhận đặt phòng.

## Thanh toán tại quầy
- Tiền mặt (VNĐ).
- Thẻ tín dụng / ghi nợ qua máy POS.

## Lưu ý
- Thanh toán trực tuyến phải hoàn tất trong vòng **24 giờ** sau khi đặt phòng, nếu không đặt phòng sẽ tự động hủy.
- Hóa đơn VAT được cung cấp theo yêu cầu (thông báo trước khi thanh toán).
- Đặt cọc **200.000 VNĐ** được thu khi nhận phòng và hoàn trả khi trả phòng.
- Hostel không chấp nhận thanh toán bằng ngoại tệ.

## Liên hệ
- Email: support@soleilhostel.vn
- Hotline: 0909-123-456
EOT,
                'category' => 'payment',
                'language' => 'vi',
                'is_active' => true,
                'last_verified_at' => $verifiedAt,
                'version' => '1.0.0',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($documents as $doc) {
            DB::table('policy_documents')->updateOrInsert(
                ['slug' => $doc['slug']],
                $doc,
            );
        }
    }
}
