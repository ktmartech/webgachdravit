<?php
/**
 * Đóng khung trang tài khoản + cột phải: lợi ích tài khoản, nhắc "không cần tài khoản để đặt hàng", Hotline/Zalo.
 */
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;

defined('ABSPATH') || exit;

$hotline = (string) drv_setting('contact.hotline', '');
$tel     = (string) preg_replace('/[^0-9+]/', '', $hotline);
$zalo    = (string) preg_replace('/\D/', '', (string) drv_setting('contact.zalo', ''));
$hours   = (string) drv_setting('contact.working_hours', '');
$benefits = [
    ['icon' => 'package', 'title' => 'Theo dõi đơn hàng', 'text' => 'Xem trạng thái xác nhận, phí vận chuyển và lịch giao của từng đơn.'],
    ['icon' => 'map-pin', 'title' => 'Lưu địa chỉ công trình', 'text' => 'Điền sẵn Tỉnh/Thành, Phường/Xã cho lần đặt sau.'],
    ['icon' => 'stack', 'title' => 'Đặt thêm cùng mã lô', 'text' => 'Xem lại lịch sử mua để đặt bổ sung đúng mã gạch, đúng lô màu.'],
];
?>
            </div>

            <aside class="drv-auth-aside" aria-label="Lợi ích tài khoản và hỗ trợ">
                <div class="drv-auth-aside__note" role="note">
                    <i class="ph ph-info" aria-hidden="true"></i>
                    <p>
                        <strong>Không cần tài khoản để đặt hàng.</strong>
                        Quý khách vẫn có thể <a href="<?php echo esc_url(MegaMenuRenderer::collection_url('gach')); ?>">chọn gạch</a> và đặt ngay; nhân viên sẽ gọi xác nhận như bình thường.
                    </p>
                </div>

                <div class="drv-auth-benefits">
                    <h2 class="drv-auth-benefits__title">Tài khoản giúp gì cho Quý khách?</h2>
                    <ul class="drv-auth-benefits__list">
                        <?php foreach ($benefits as $b) : ?>
                            <li class="drv-auth-benefit">
                                <span class="drv-auth-benefit__icon" aria-hidden="true"><i class="ph ph-<?php echo esc_attr($b['icon']); ?>"></i></span>
                                <span class="drv-auth-benefit__body">
                                    <strong><?php echo esc_html($b['title']); ?></strong>
                                    <span><?php echo esc_html($b['text']); ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if ($tel !== '' || $zalo !== '') : ?>
                    <div class="drv-auth-help">
                        <p class="drv-auth-help__title">Cần hỗ trợ đăng nhập?</p>
                        <?php if ($hours !== '') : ?>
                            <p class="drv-auth-help__hours"><?php echo esc_html($hours); ?></p>
                        <?php endif; ?>
                        <div class="drv-auth-help__actions">
                            <?php if ($tel !== '') : ?>
                                <a href="<?php echo esc_url('tel:' . $tel); ?>" class="drv-btn drv-btn--outline drv-btn--sm u-tabular">
                                    <i class="ph ph-phone-call drv-icon--xs" aria-hidden="true"></i>
                                    <span><?php echo esc_html($hotline); ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($zalo !== '') : ?>
                                <a href="<?php echo esc_url('https://zalo.me/' . $zalo); ?>" target="_blank" rel="noopener" class="drv-btn drv-btn--moss drv-btn--sm">
                                    <i class="ph-bold ph-chat-circle-dots drv-icon--xs" aria-hidden="true"></i>
                                    <span>Chat Zalo</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>
