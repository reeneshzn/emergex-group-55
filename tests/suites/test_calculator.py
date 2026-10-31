"""
Move-in cost estimator — TC-23 to TC-29.

Verifies FR8 and business rule BR-13:

    Move-in cost = rent for the lease term
                   (less 5% upfront, or 2% termly, ON RENT ONLY)
                 + deposit (rent x chosen deposit months)
                 + a fixed R950 admin fee,
                   all in whole rand.

The rule that carries the most meaning is the parenthesis: a discount
applies to rent and never to the deposit or the admin fee. A deposit is
refundable, so discounting it would misrepresent what is actually held.

These are the only tests in the suite that assert arithmetic rather than
behaviour, so each expected figure is calculated here independently
rather than read from the page. If the two ever disagree, one of them is
wrong and that is exactly what the test is for.
"""

import pytest

from support import config


# The estimator's own defaults, from index.php.
DEFAULT_RENT = 4500
DEFAULT_LEASE = 12
DEFAULT_DEPOSIT_MONTHS = 1

ADMIN_FEE = config.ADMIN_FEE_ZAR  # 950


@pytest.fixture
def calculator(home):
    """The landing page, scrolled to the estimator and ready to drive."""
    home.open()
    home.calc.scroll_into_view_if_needed()
    return home


# ======================================================================
# TC-23 — Monthly plan
# ======================================================================


@pytest.mark.logic
@pytest.mark.functional
def test_monthly_plan_applies_no_discount(calculator):
    """
    TC-23 · FR8, BR-13

    R4 500 a month for 12 months, one month deposit, paid monthly.

    Worked by hand:
        rent total  = 4500 x 12        = 54 000
        discount    = none             =      0
        deposit     = 4500 x 1         =  4 500
        admin       = fixed            =    950
        total       = 54000+4500+950   = 59 450
        instalment  = 54000 / 12       =  4 500
        due now     = deposit + admin + first instalment
                    = 4500 + 950 + 4500 = 9 950
    """
    calculator.set_calculator(
        rent=DEFAULT_RENT,
        lease_months=DEFAULT_LEASE,
        deposit_months=DEFAULT_DEPOSIT_MONTHS,
        plan="monthly",
    )
    figures = calculator.calculator_figures()

    assert figures["discount"] == 0, "The monthly plan carries no discount"
    assert figures["deposit"] == 4500
    assert figures["admin"] == ADMIN_FEE
    assert figures["total"] == 59450
    assert figures["instalment"] == 4500
    assert figures["due_now"] == 9950
    assert calculator.instalment_count() == 12


# ======================================================================
# TC-24 — Upfront plan
# ======================================================================


@pytest.mark.logic
@pytest.mark.functional
def test_upfront_plan_discounts_five_per_cent_of_rent(calculator):
    """
    TC-24 · FR8, BR-13

    Worked by hand:
        gross rent  = 4500 x 12        = 54 000
        discount    = 5% of 54 000     =  2 700
        rent total  = 54000 - 2700     = 51 300
        deposit     = unchanged        =  4 500
        admin       = unchanged        =    950
        total       = 51300+4500+950   = 56 750
        due now     = the whole total, paid at once
    """
    calculator.set_calculator(
        rent=DEFAULT_RENT,
        lease_months=DEFAULT_LEASE,
        deposit_months=DEFAULT_DEPOSIT_MONTHS,
        plan="upfront",
    )
    figures = calculator.calculator_figures()

    assert figures["discount"] == 2700
    assert figures["total"] == 56750
    assert figures["due_now"] == figures["total"], (
        "Paying upfront means the whole amount is due now"
    )

    # There is only one payment, so the estimator does not label it with a
    # count the way it does for monthly and termly - "12 x monthly
    # instalment" has no sensible single-payment equivalent. Assert the
    # behaviour (one payment, nothing left over) rather than the wording.
    assert calculator.instalment_count() in (0, 1), (
        "The upfront plan should describe a single payment, not several"
    )


# ======================================================================
# TC-25 — Termly plan
# ======================================================================


@pytest.mark.logic
@pytest.mark.functional
def test_termly_plan_discounts_two_per_cent(calculator):
    """
    TC-25 · FR8, BR-13

        discount   = 2% of 54 000      = 1 080
        rent total = 54000 - 1080      = 52 920
        terms      = 12 months / 3     = 4
        instalment = 52920 / 4         = 13 230
    """
    calculator.set_calculator(
        rent=DEFAULT_RENT,
        lease_months=DEFAULT_LEASE,
        deposit_months=DEFAULT_DEPOSIT_MONTHS,
        plan="termly",
    )
    figures = calculator.calculator_figures()

    assert figures["discount"] == 1080
    assert calculator.instalment_count() == 4
    assert figures["instalment"] == 13230


@pytest.mark.logic
@pytest.mark.functional
@pytest.mark.parametrize(
    "months, expected_terms",
    [(6, 2), (10, 4), (12, 4)],
    ids=["6mo-exact", "10mo-rounds-up", "12mo-exact"],
)
def test_termly_instalment_count_rounds_up(calculator, months, expected_terms):
    """
    TC-25 · BR-13 — term count

    A term is three months, and a partial term is still a term. Ten
    months is three full terms plus one month, so four payments rather
    than three — rounding down would leave a month unpaid.

    These are the only three lease lengths the estimator offers, so the
    set is complete rather than a sample.
    """
    calculator.set_calculator(lease_months=months, plan="termly")
    assert calculator.instalment_count() == expected_terms, (
        f"A {months}-month lease should be billed over {expected_terms} terms"
    )


# ======================================================================
# TC-26 — The deposit is never discounted
# ======================================================================


@pytest.mark.logic
@pytest.mark.functional
def test_deposit_is_identical_under_every_plan(calculator):
    """
    TC-26 · BR-13

    The most meaningful rule in the calculation. A deposit is refundable
    money held on the student's behalf — discounting it would misstate
    what is actually being held, so the discount must touch rent only.

    Proved by taking the deposit under all three plans with everything
    else unchanged: all three must be equal.
    """
    deposits = {}
    for plan in ("monthly", "termly", "upfront"):
        calculator.set_calculator(
            rent=DEFAULT_RENT,
            lease_months=DEFAULT_LEASE,
            deposit_months=DEFAULT_DEPOSIT_MONTHS,
            plan=plan,
        )
        deposits[plan] = calculator.calculator_figures()["deposit"]

    assert len(set(deposits.values())) == 1, (
        f"The deposit changed with the payment plan: {deposits}. "
        "BR-13 says the discount applies to rent only."
    )
    assert deposits["monthly"] == DEFAULT_RENT * DEFAULT_DEPOSIT_MONTHS


@pytest.mark.logic
@pytest.mark.functional
def test_deposit_scales_with_the_chosen_months(calculator):
    """
    TC-26 · BR-13 — deposit = rent x deposit months.
    """
    calculator.set_calculator(
        rent=DEFAULT_RENT, deposit_months=1, plan="monthly"
    )
    one_month = calculator.calculator_figures()["deposit"]

    calculator.set_calculator(deposit_months=2)
    two_months = calculator.calculator_figures()["deposit"]

    assert one_month == DEFAULT_RENT
    assert two_months == DEFAULT_RENT * 2


@pytest.mark.logic
@pytest.mark.functional
def test_no_deposit_option_charges_nothing_but_still_charges_the_fee(calculator):
    """
    TC-26 · BR-13 — the zero boundary.

    'No deposit' must produce a deposit of exactly zero, not a blank or
    a fallback to one month. The admin fee is separate and must still
    appear, because it is not a deposit.
    """
    calculator.set_calculator(
        rent=DEFAULT_RENT,
        lease_months=DEFAULT_LEASE,
        deposit_months=0,
        plan="monthly",
    )
    f = calculator.calculator_figures()

    assert f["deposit"] == 0, "Selecting no deposit still charged one"
    assert f["admin"] == ADMIN_FEE, "The admin fee is not a deposit and still applies"
    assert f["total"] == DEFAULT_RENT * DEFAULT_LEASE + ADMIN_FEE


# ======================================================================
# TC-27 — The admin fee
# ======================================================================


@pytest.mark.logic
@pytest.mark.functional
@pytest.mark.parametrize("plan", ["monthly", "termly", "upfront"])
def test_admin_fee_is_fixed_and_never_discounted(calculator, plan):
    """
    TC-27 · BR-13

    R950, every time, under every plan and at every rent. It is a
    once-off fee for processing, not a proportion of anything, so a
    discount must not reduce it.
    """
    calculator.set_calculator(rent=DEFAULT_RENT, plan=plan)
    assert calculator.calculator_figures()["admin"] == ADMIN_FEE

    # And again at a different rent, to prove it is not proportional.
    calculator.set_calculator(rent=8000)
    assert calculator.calculator_figures()["admin"] == ADMIN_FEE


@pytest.mark.logic
@pytest.mark.functional
@pytest.mark.parametrize(
    "plan, discount_rate",
    [("monthly", 0.0), ("termly", 0.02), ("upfront", 0.05)],
)
def test_total_is_the_sum_of_its_parts(calculator, plan, discount_rate):
    """
    TC-27 · BR-13

    Whatever the plan, the total must be:

        discounted rent + deposit + admin fee

    The rent portion is derived by subtracting the deposit and the fee
    from the total, then checked against the rule's own arithmetic. That
    avoids depending on the instalment count, which the upfront plan
    does not display - there is only one payment, so there is no count
    to show.
    """
    calculator.set_calculator(
        rent=DEFAULT_RENT,
        lease_months=DEFAULT_LEASE,
        deposit_months=DEFAULT_DEPOSIT_MONTHS,
        plan=plan,
    )
    f = calculator.calculator_figures()

    gross_rent = DEFAULT_RENT * DEFAULT_LEASE
    expected_rent = round(gross_rent - round(gross_rent * discount_rate))

    rent_portion = f["total"] - f["deposit"] - f["admin"]

    assert rent_portion == expected_rent, (
        f"On the {plan} plan the rent portion of the total is {rent_portion}, "
        f"but {gross_rent} less a {discount_rate:.0%} discount is {expected_rent}"
    )
    assert f["discount"] == round(gross_rent * discount_rate), (
        f"The {plan} plan reports a discount of {f['discount']}, "
        f"expected {round(gross_rent * discount_rate)}"
    )


# ======================================================================
# TC-28 — Edge cases
# ======================================================================


@pytest.mark.logic
@pytest.mark.functional
def test_lowest_and_highest_rent_produce_sane_figures(calculator):
    """
    TC-28 · BR-13 — boundaries of the rent control.

    The slider runs 2000 to 9000. At both ends every figure must still be
    a positive whole number, with the total exceeding the rent alone
    because the deposit and fee are added.
    """
    for rent in (config.RENT_FILTER_MIN, config.RENT_FILTER_MAX):
        calculator.set_calculator(
            rent=rent, lease_months=DEFAULT_LEASE, deposit_months=1, plan="monthly"
        )
        f = calculator.calculator_figures()

        assert f["deposit"] == rent
        assert f["admin"] == ADMIN_FEE
        assert f["total"] > rent * DEFAULT_LEASE
        assert all(v >= 0 for v in f.values()), f"Negative figure at rent {rent}: {f}"


@pytest.mark.logic
def test_no_figure_is_blank_or_not_a_number(calculator):
    """
    TC-28 · BR-13

    A division that goes wrong shows as NaN or an empty box rather than
    an error, which is easy to miss by eye. Every figure must read as a
    rand amount.
    """
    calculator.set_calculator(rent=DEFAULT_RENT, plan="termly")

    for name, locator in [
        ("due now", calculator.calc_due_now),
        ("instalment", calculator.calc_instalment),
        ("deposit", calculator.calc_deposit_out),
        ("admin", calculator.calc_admin),
        ("total", calculator.calc_total),
    ]:
        text = locator.inner_text().strip()
        assert text, f"The {name} figure is blank"
        assert "nan" not in text.lower(), f"The {name} figure reads {text!r}"
        assert any(ch.isdigit() for ch in text), f"The {name} figure has no number"


# ======================================================================
# TC-29 — Presentation of money
# ======================================================================


@pytest.mark.ui
@pytest.mark.regression
def test_every_figure_is_whole_rand_in_the_house_format(calculator):
    """
    TC-29 · BR-13

    Whole rand only, formatted 'R 59 450' — an R, a space, and digits
    grouped in threes by spaces. No decimal point, because the system
    works in whole rand throughout and a stray cents value would mean
    the rounding has drifted somewhere.
    """
    import re

    calculator.set_calculator(rent=DEFAULT_RENT, plan="monthly")

    pattern = re.compile(r"^R\s?\d{1,3}(\s\d{3})*$")

    for name, locator in [
        ("due now", calculator.calc_due_now),
        ("instalment", calculator.calc_instalment),
        ("deposit", calculator.calc_deposit_out),
        ("admin", calculator.calc_admin),
        ("total", calculator.calc_total),
    ]:
        text = locator.inner_text().strip()
        assert pattern.match(text), (
            f"The {name} figure reads {text!r}, which is not the house "
            "format (expected something like 'R 59 450')"
        )
        assert "." not in text and "," not in text, (
            f"The {name} figure {text!r} shows a decimal or comma; "
            "the system works in whole rand"
        )


@pytest.mark.ui
def test_the_discount_line_appears_only_when_there_is_one(calculator):
    """
    TC-29 · BR-13, NFR5

    Showing 'Discount on rent: R 0' on the monthly plan would imply a
    discount exists when none does. The row is hidden instead.
    """
    calculator.set_calculator(plan="monthly")
    assert not calculator.calc_discount_row.is_visible(), (
        "A discount line is shown on the monthly plan, where there is no discount"
    )

    calculator.set_calculator(plan="upfront")
    assert calculator.calc_discount_row.is_visible(), (
        "No discount line is shown on the upfront plan, which does discount"
    )
    assert calculator.calculator_figures()["discount"] > 0
