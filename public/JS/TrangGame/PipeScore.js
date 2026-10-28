(function (root) {
  'use strict';
  function score(lengths, baseline) {
    const unknown = { quality_score: null, official_score: 0, raw_score: null,
      is_optimal: false, score_status: baseline?.status || 'unproven' };
    if (lengths.length && lengths.every(n => n === null) && baseline?.status !== 'invalid') {
      return { ...unknown, quality_score: 0, raw_score: 0 };
    }
    if (!lengths.length || baseline?.status !== 'proven') return unknown;
    let best = null, selected = null;
    for (const vector of baseline.vectors || []) {
      if (vector.length !== lengths.length || vector.some(n => !Number.isInteger(n) || n <= 0)) continue;
      let sum = 0, compatible = true;
      for (let i = 0; i < lengths.length; i++) {
        const length = lengths[i];
        if (length === null) continue;
        if (!Number.isInteger(length) || length <= 0) return { ...unknown, score_status: 'invalid_lengths' };
        if (length < vector[i]) { compatible = false; break; }
        sum += 100 * (vector[i] / length) ** 2;
      }
      if (compatible && (best === null || sum / lengths.length > best)) {
        best = sum / lengths.length; selected = vector;
      }
    }
    if (best === null) return { ...unknown, score_status: 'incomparable_lengths' };
    return { quality_score: Math.round(best), official_score: Math.round(best), raw_score: best,
      is_optimal: lengths.every((n, i) => n === selected[i]), score_status: 'proven' };
  }
  root.PipeScore = { score };
  if (typeof module !== 'undefined') module.exports = root.PipeScore;
})(typeof window === 'undefined' ? globalThis : window);
