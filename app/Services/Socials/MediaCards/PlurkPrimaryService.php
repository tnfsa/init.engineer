<?php

namespace App\Services\Socials\MediaCards;

use Exception;
use Qlurk\ApiClient;
use App\Models\Auth\User;
use App\Models\Social\Cards;
use App\Services\BaseService;
use App\Exceptions\GeneralException;
use App\Repositories\Backend\Social\MediaCardsRepository;
use Illuminate\Support\Str;

/**
 * Class PlurkPrimaryService.
 */
class PlurkPrimaryService extends BaseService implements SocialCardsContract
{
    /**
     * @var ApiClient
     */
    protected $plurk;

    /**
     * @var MediaCardsRepository
     */
    protected $mediaCardsRepository;

    /**
     * PlurkPrimaryService constructor.
     */
    public function __construct(MediaCardsRepository $mediaCardsRepository)
    {
        $this->plurk = new ApiClient(
            env('PLURK_CLIENT_ID'),
            env('PLURK_CLIENT_SECRET'),
            env('PLURK_TOKEN'),
            env('PLURK_TOKEN_SECRET')
        );
        $this->mediaCardsRepository = $mediaCardsRepository;
    }

    /**
     * 注意: Plurk 的內容如果超過中英文 360 字的話，多餘的內容將會被 Plurk 自動忽略。
     *
     * @param Cards $cards
     * @return MediaCards
     */
    public function publish(Cards $cards)
    {
        if ($this->mediaCardsRepository->findByCardId($cards->id, 'plurk', 'primary')) {
            throw new GeneralException(__('exceptions.backend.social.media.cards.repeated_error'));
        } else {
            try {
                $picture = $this->plurk->call('/APP/Timeline/uploadPicture', [
                    'image' => $cards->images->first()->getFile(),
                ]);
                $response = $this->plurk->call('/APP/Timeline/plurkAdd', [
                    'content'   => $this->buildContent($cards->content, [
                        'id' => $cards->id,
                        'image_url' => $picture['full'],
                    ]),
                    'qualifier' => 'says',
                    'lang'      => 'tr_ch'
                ]);

                return $this->mediaCardsRepository->create([
                    'card_id' => $cards->id,
                    'model_id' => $cards->model_id,
                    'social_type' => 'plurk',
                    'social_connections' => 'primary',
                    'social_card_id' => base_convert($response['plurk_id'], 10, 36),
                ]);
            } catch (Exception $e) {
                \Log::error($e->getMessage());
            }
        }
    }

    /**
     * @param Cards $cards
     * @return MediaCards
     */
    public function update(Cards $cards)
    {
        if ($mediaCards = $this->mediaCardsRepository->findByCardId($cards->id, 'plurk', 'primary')) {
            try {
                $response = $this->plurk->call('/APP/Timeline/getPlurk', [
                    'plurk_id'   => base_convert($mediaCards->social_card_id, 36, 10),
                    'count'      => 'all'
                ]);
                return $this->mediaCardsRepository->update($mediaCards, [
                    'num_like' => $response['plurk']['favorite_count'],
                    'num_share' => $response['plurk']['replurkers_count'],
                ]);
            } catch (Exception $e) {
                \Log::error($e->getMessage());
            }
        }

        return false;
    }

    /**
     * @param User  $user
     * @param Cards $cards
     * @param array $options
     * @return MediaCards
     */
    public function destory(User $user, Cards $cards, array $options)
    {
        if ($mediaCards = $this->mediaCardsRepository->findByCardId($cards->id, 'plurk', 'primary')) {
            try {
                $response = $this->plurk->call('/APP/Timeline/plurkDelete', ['plurk_id' => base_convert($mediaCards->social_card_id, 36, 10)]);

                // TODO: 解析 response 的資訊

                return $this->mediaCardsRepository->update($mediaCards, [
                    'active' => false,
                    'is_banned' => true,
                    'banned_user_id' => $user->id,
                    'banned_remarks' => isset($options['remarks']) ? $options['remarks'] : null,
                    'banned_at' => now(),
                ]);
            } catch (\Facebook\Exceptions\FacebookSDKException $e) {
                \Log::error($e->getMessage());
            } catch (Exception $e) {
                \Log::error($e->getMessage());
            }
        }

        return false;
    }

    /**
     * @param string $content
     * @return string
     */
    public function buildContent($content = '', array $options = [])
    {
        $_content = Str::limit($content, 100, ' ...');

        return $options['image_url'] . "\n\r" .
            '#靠北南一中' . base_convert($options['id'], 10, 36) . "\n\r----------\n\r" .
            $_content . "\n\r" .
            "\n\r----------\n\r" .
            "💖 純靠北 官方 Discord 歡迎在這找到你的同溫層！\n\r" .
            "👉 https://discord.gg/tPhnrs2" .
            "\n\r----------\n\r" .
            "💖 全平台留言、文章詳細內容\n\r" .
            "👉 https://kaobei.tnfsa.org/cards/show/" . $options['id'];

        // return $options['image_url'] . "\n\r" .
        //     '#靠北南一中' . base_convert($options['id'], 10, 36) . "\n\r----------\n\r" .
        //     $_content . "\n\r----------\n\r" .
        //     '🗳️ [群眾審核] ' . route('frontend.social.cards.review') . '?' . Str::random(4) . "\n\r" .
        //     '👉 [GitHub Repo] https://github.com/init-engineer/init.engineer' . '?' . Str::random(4) . "\n\r" .
        //     '📢 [匿名發文] ' . route('frontend.social.cards.create') . '?' . Str::random(4) . "\n\r" .
        //     '🥙 [全平台留言] ' . route('frontend.social.cards.show', ['id' => $options['id']]);

        // return sprintf(
        //     "%s\r\n#靠北南一中%s\r\n%s\r\n%s\r\n🥙 全平台留言 %s",
        //     $options['image_url'],
        //     base_convert($options['id'], 10, 36),
        //     $_content,
        //     '👉 去 GitHub 給我們🌟用行動支持靠北南一中 https://github.com/init-engineer/init.engineer',
        //     route('frontend.social.cards.show', ['id' => $options['id']])
        // );
    }
}
