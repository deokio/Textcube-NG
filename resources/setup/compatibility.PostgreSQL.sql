-- Textcube-NG: PostgreSQL 12 이상 호환으로 다시 작성(abstime 형 제거됨). 설치 화면과 점검 화면은 이 파일을 세미콜론으로 나눠 실행하므로 함수 본문에는 세미콜론을 두지 않는다
CREATE OR REPLACE FUNCTION from_unixtime(integer) RETURNS timestamp AS $$
SELECT
to_timestamp($1)::timestamp without time zone AS result
$$ LANGUAGE sql;
-- Textcube-NG: from_unixtime 에 bigint·numeric 인자 오버로드를 더한다(2038년 이후 값·소수 epoch 가 function does not exist 로 실패하던 문제)(v3.183)
CREATE OR REPLACE FUNCTION from_unixtime(bigint) RETURNS timestamp AS $$
SELECT
to_timestamp($1)::timestamp without time zone AS result
$$ LANGUAGE sql;
CREATE OR REPLACE FUNCTION from_unixtime(numeric) RETURNS timestamp AS $$
SELECT
to_timestamp($1)::timestamp without time zone AS result
$$ LANGUAGE sql;
CREATE OR REPLACE FUNCTION unix_timestamp() RETURNS integer AS $$
SELECT
FLOOR(EXTRACT( EPOCH FROM now() ))::int4 AS result
$$ LANGUAGE sql;
CREATE OR REPLACE FUNCTION unix_timestamp(timestamp with time zone) RETURNS integer AS $$
SELECT
FLOOR(EXTRACT( EPOCH FROM $1 ))::int4 AS result
$$ LANGUAGE sql;
CREATE OR REPLACE FUNCTION dayofmonth(timestamp without time zone) RETURNS numeric AS $$
SELECT
to_number(to_char($1, 'DD'),'99') AS result
$$ LANGUAGE sql;
CREATE OR REPLACE FUNCTION year(timestamp without time zone) RETURNS numeric AS $$
SELECT
to_number(to_char($1, 'YYYY'),'9999') AS result
$$ LANGUAGE sql;
CREATE OR REPLACE FUNCTION month(timestamp without time zone) RETURNS numeric AS $$
SELECT
to_number(to_char($1, 'MM'),'99') AS result
$$ LANGUAGE sql;