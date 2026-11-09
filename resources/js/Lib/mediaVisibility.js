export function shouldBlurMedia(rating, showExplicit, revealed = false) {
    return rating === 'explicit' && !showExplicit && !revealed;
}
