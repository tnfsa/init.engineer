<?php

namespace App\Services\Socials\MediaCards;

use Exception;
use App\Models\Auth\User;
use App\Models\Social\Cards;
use App\Services\BaseService;
use App\Exceptions\GeneralException;
use App\Repositories\Backend\Social\MediaCardsRepository;
use Tumblr\API\Client as TumblrClient;

/**
 * Class TumblrPrimaryService.
 */
class TumblrPrimaryService extends BaseService implements SocialCardsContract
{
    /**
     * @var TumblrClient
     */
    protected $tumblr;

    /**
     * @var MediaCardsRepository
     */
    protected $mediaCardsRepository;

    /**
     * TumblrPrimaryService constructor.
     */
    public function __construct(MediaCardsRepository $mediaCardsRepository)
    {
        $this->mediaCardsRepository = $mediaCardsRepository;

        $this->tumblr = new TumblrClient(
            config('tumblr.CONSUMER_KEY'),
            config('tumblr.CONSUMER_SECRET')
        );
        $this->tumblr->setToken(
            config('tumblr.ACCESS_TOKEN'),
            config('tumblr.ACCESS_TOKEN_SECRET')
        );
    }

    /**
     * @param Cards $cards
     * @return MediaCards
     */
    public function publish(Cards $cards)
    {
        if ($this->mediaCardsRepository->findByCardId($cards->id, 'tumblr', 'primary')) {
            throw new GeneralException(__('exceptions.backend.social.media.cards.repeated_error'));
        } else {
            try {
                $content = $this->buildContent($cards->content, [
                    'id' => $cards->id,
                ]);

                $photoPost = [
                    'data' => $cards->images->first()->getPicture(),
                    'type' => 'photo',
                    'format' => 'html',
                    'caption' => $content,
                ];

                $response = $this->tumblr->createPost(config('social.tumblr.primary.user_id'), $photoPost);

                return $this->mediaCardsRepository->create([
                    'card_id' => $cards->id,
                    'model_id' => $cards->model_id,
                    'social_type' => 'tumblr',
                    'social_connections' => 'primary',
                    'social_card_id' => $response->id,
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
        if ($mediaCards = $this->mediaCardsRepository->findByCardId($cards->id, 'tumblr', 'primary')) {
            try {
                $params = [
                    'id' => $mediaCards->social_card_id,
                    'mode' => 'all'
                ];

                $url = sprintf('v2/blog/%s/notes', config('social.tumblr.primary.user_id'));

                $response = $this->tumblr->getRequest($url, $params, null);

                $count = collect($response->notes)->countBy(function ($item) {
                    return $item->type;
                });

                return $this->mediaCardsRepository->update($mediaCards, [
                    'num_like' => $count['like'],
                    'num_share' => $count['reblog'],
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
        if ($mediaCards = $this->mediaCardsRepository->findByCardId($cards->id, 'tumblr', 'primary')) {
            try {
                $response = $this->tumblr->deletePost(
                    config('social.tumblr.primary.user_id'),
                    $mediaCards->social_card_id,
                    $mediaCards->social_card_id
                );

                // TODO: 解析 response 的資訊

                return $this->mediaCardsRepository->update($mediaCards, [
                    'active' => false,
                    'is_banned' => true,
                    'banned_user_id' => $user->id,
                    'banned_remarks' => isset($options['remarks']) ? $options['remarks'] : null,
                    'banned_at' => now(),
                ]);
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
    public function buildContent($content = '', array $options = []): string
    {
        return '<div>#靠北南一中' . base_convert($options['id'], 10, 36) . '<br /><hr /><br />' .
            '<div>' . nl2br($content) . '</div><br /><hr /><br />' .
            '<p>💖 純靠北 官方 Discord 歡迎在這找到你的同溫層！</p>' .
            '<p>👉 <a href="https://discord.gg/tPhnrs2">https://discord.gg/tPhnrs2</a></p>' .
            '<br /><hr /><br />' .
            '<p>💖 全平台留言、文章詳細內容</p>' .
            '<p>👉 <a href="' . "https://kaobei.tnfsa.org/cards/show/" . $options['id'] . '">' . "https://kaobei.tnfsa.org/cards/show/" . $options['id'] . '</a></p>';
        // return '<div>#靠北南一中' . base_convert($options['id'], 10, 36) . '<br /><hr /><br />' .
        //     '<div>' . nl2br($content) . '</div><br /><hr /><br />' .
        //     '<p>🗳️ [群眾審核] <a href="' . route('frontend.social.cards.review') . '">' . route('frontend.social.cards.create') . '</a></p>' .
        //     '<p>👉 [GitHub] <a href="https://github.com/init-engineer/init.engineer">init-engineer/init.engineer</a></p>' .
        //     '<p>📢 [匿名發文] <a href="' . route('frontend.social.cards.create') . '">' . route('frontend.social.cards.create') . '</a></p>' .
        //     '<p>🥙 [全平台留言] <a href="' . route('frontend.social.cards.show', ['id' => $options['id']]) . '">' . route('frontend.social.cards.show', ['id' => $options['id']]) . '</a></p>';
    }
}
