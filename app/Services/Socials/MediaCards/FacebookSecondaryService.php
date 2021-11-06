<?php

namespace App\Services\Socials\MediaCards;

use Exception;
use Facebook\FacebookApp;
use App\Models\Auth\User;
use App\Models\Social\Cards;
use App\Services\BaseService;
use Facebook\FacebookRequest;
use App\Exceptions\GeneralException;
use Vinkla\Facebook\Facades\Facebook;
use App\Repositories\Backend\Social\MediaCardsRepository;

/**
 * Class FacebookSecondaryService.
 */
class FacebookSecondaryService extends BaseService implements SocialCardsContract
{
    /**
     * @var Facebook
     */
    protected $facebook;

    /**
     * @var MediaCardsRepository
     */
    protected $mediaCardsRepository;

    /**
     * FacebookSecondaryService constructor.
     */
    public function __construct(MediaCardsRepository $mediaCardsRepository)
    {
        $this->mediaCardsRepository = $mediaCardsRepository;
        $this->facebook = Facebook::connection('secondary');
    }

    /**
     * @param Cards $cards
     * @throws \App\Exceptions\GeneralException
     * @return MediaCards
     */
    public function publish(Cards $cards)
    {
        if ($this->mediaCardsRepository->findByCardId($cards->id, 'facebook', 'secondary')) {
            throw new GeneralException(__('exceptions.backend.social.media.cards.repeated_error'));
        } else {
            try {
                $this->getAccessToken();
                $response = $this->facebook->post(
                    sprintf(
                        '/%s/photos',
                        config('facebook.connections.secondary.user_id', 'FACEBOOK_CONNECTIONS_SECONDARY_USER_ID')
                    ),
                    [
                        'message' => $this->buildContent($cards->content, [
                            'id' => $cards->id,
                        ]),
                        'source' => $this->facebook->fileToUpload($cards->images->first()->getPicture()),
                    ],
                );

                return $this->mediaCardsRepository->create([
                    'card_id' => $cards->id,
                    'model_id' => $cards->model_id,
                    'social_type' => 'facebook',
                    'social_connections' => 'secondary',
                    'social_card_id' => $response->getGraphUser()->getId(),
                ]);
            } catch (\Facebook\Exceptions\FacebookSDKException $e) {
                \Log::error($e->getMessage());
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
        if ($mediaCards = $this->mediaCardsRepository->findByCardId($cards->id, 'facebook', 'secondary')) {
            try {
                $this->getAccessToken();
                $response = $this->facebook->get(
                    sprintf(
                        '%s_%s?fields=shares,likes.summary(true).limit(0),reactions.type(LIKE).limit(0).summary(total_count).as(reactions_like),reactions.type(LOVE).limit(0).summary(total_count).as(reactions_love),reactions.type(WOW).limit(0).summary(total_count).as(reactions_wow),reactions.type(HAHA).limit(0).summary(total_count).as(reactions_haha),reactions.type(SAD).limit(0).summary(total_count).as(reactions_sad),reactions.type(ANGRY).limit(0).summary(total_count).as(reactions_angry)',
                        config('facebook.connections.secondary.user_id', 'FACEBOOK_CONNECTIONS_SECONDARY_USER_ID'),
                        $mediaCards->social_card_id
                    )
                );
                $decodedBody = $response->getDecodedBody();
                return $this->mediaCardsRepository->update($mediaCards, [
                    'num_like' => $this->slicerCardsLikes($decodedBody),
                    'num_share' => $this->slicerCardsShare($decodedBody),
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
     * @param User  $user
     * @param Cards $cards
     * @param array $options
     * @return MediaCards
     */
    public function destory(User $user, Cards $cards, array $options)
    {
        if ($mediaCards = $this->mediaCardsRepository->findByCardId($cards->id, 'facebook', 'secondary')) {
            try {
                $this->getAccessToken();
                $response = $this->facebook->delete(sprintf('/%s', $mediaCards->social_card_id));
                $decodedBody = $response->getDecodedBody();

                // TODO: 解析 decodedBody 的資訊

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
        return '#靠北南一中' . base_convert($options['id'], 10, 36) . "\n\r----------\n\r" .
            $content . "\n\r" .
            "\n\r----------\n\r" .
            "💖 純靠北官方 Discord 歡迎在這找到你的同溫層！\n\r" .
            "👉 https://discord.gg/tPhnrs2\n\r" .
            "\n\r----------\n\r" .
            "💖 全平台留言、文章詳細內容\n\r" .
            "👉 https://kaobei.tnfsa.org/cards/show/" . $options['id'];


        // return '#靠北南一中' . base_convert($options['id'], 10, 36) . "\n\r----------\n\r" .
        //     $content . "\n\r----------\n\r" .
        //     '🗳️ [群眾審核] ' . route('frontend.social.cards.review') . "\n\r" .
        //     '👉 [GitHub Repo] https://github.com/init-engineer/init.engineer' . "\n\r" .
        //     '📢 [匿名發文] ' . route('frontend.social.cards.create') . "\n\r" .
        //     '🥙 [全平台留言] ' . route('frontend.social.cards.show', ['id' => $options['id']]);

        // return sprintf(
        //     "%s\r\n%s\r\n%s\r\n%s\r\n%s\r\n%s",
        //     '#靠北南一中' . base_convert($options['id'], 10, 36),
        //     $content,
        //     '👉 去 GitHub 給我們🌟用行動支持靠北南一中 https://github.com/init-engineer/init.engineer',
        //     '📢 匿名發文請至 ' . route('frontend.social.cards.create'),
        //     '🥙 全平台留言 ' . route('frontend.social.cards.show', ['id' => $options['id']])
        // );
    }

    /**
     * --------------------------------------------------------------------------------
     * ## 問題描述
     * --------------------------------------------------------------------------------
     * Facebook 發表文章的流程因 Facebook 所頒布的新政策關係，導致無法正常發表文章。
     *
     * 圖形 API 3.0 版已停用 publish_actions 權限。
     * 應用程式仍可透過中介式體驗（例如網路上的 Facebook「分享」對話方塊）或 iOS 和 Android 的「Share Sheets」發佈動態。
     * 應用程式可利用 publish_groups 權限發佈到群組，但應用程式必須通過審查才能取得該權限。
     *
     * 會發生這種情況的原因，是因為 Access Token 使用的是 Pages Token(粉絲專頁的 Token)，今後 Facebook 將禁用這方面的權限。
     *
     * --------------------------------------------------------------------------------
     * ## 解決方案
     * --------------------------------------------------------------------------------
     * 透過使用者的 Token 來抓取 Access Token，然後動態去抓 Pages Token 來發表文章。
     * 這個動作必須每次發文時都要執行一次，因為產生出來的 Pages Token 時效性很短，可能不到一小時。
     *
     * https://github.com/init-engineer/init.engineer/issues/2
     */
    private function getAccessToken()
    {
        try {
            $facebookApp = new FacebookApp(
                $this->facebook->getApp()->getId(),
                $this->facebook->getApp()->getSecret()
            );

            $facebookRequest = new FacebookRequest(
                $facebookApp,
                $this->facebook->getDefaultAccessToken()->getValue(),
                'GET',
                config('facebook.connections.secondary.user_id', 'FACEBOOK_CONNECTIONS_SECONDARY_USER_ID'),
                ['fields' => 'access_token']
            );

            $accessToken = $this->facebook->getClient()->sendRequest($facebookRequest)->getDecodedBody();
            $foreverPageAccessToken = $accessToken['access_token'];
            $this->facebook->setDefaultAccessToken($foreverPageAccessToken);
        } catch (\Facebook\Exceptions\FacebookSDKException $e) {
            \Log::error($e->getMessage());
        } catch (Exception $e) {
            \Log::error($e->getMessage());
        }
    }

    /**
     * @param array $body
     * @return int
     */
    private function slicerCardsLikes($body): int
    {
        $fb_like  = (!empty($body['reactions_like'])) ? $body['reactions_like']['summary']['total_count']  : 0;
        $fb_love  = (!empty($body['reactions_love'])) ? $body['reactions_love']['summary']['total_count']  : 0;
        $fb_wow   = (!empty($body['reactions_wow']))  ? $body['reactions_wow']['summary']['total_count']   : 0;
        $fb_haha  = (!empty($body['reactions_haha'])) ? $body['reactions_haha']['summary']['total_count']  : 0;
        $fb_sad   = (!empty($body['reactions_sad']))  ? $body['reactions_sad']['summary']['total_count']   : 0;
        $fb_angry = (!empty($body['reactions_angry'])) ? $body['reactions_angry']['summary']['total_count'] : 0;
        $fb_count = $fb_like + $fb_love + $fb_wow + $fb_haha + $fb_sad + $fb_angry;

        return $fb_count;
    }

    /**
     * @param array $body
     * @return int
     */
    private function slicerCardsShare($body): int
    {
        return (!empty($body['shares'])) ? $body['shares']['count'] : 0;
    }
}
